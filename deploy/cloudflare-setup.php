<?php

/*
 * =============================================================================
 *  Configures Cloudflare for the website through the Cloudflare API:
 *    - removes old A/AAAA/CNAME records for the domain and www
 *    - routes domain + www through the tunnel to http://localhost:80
 *    - creates the tunnel CNAME records (proxied)
 *    - Always Use HTTPS: on, Rocket Loader: off
 *
 *  Run inside the website container (after deploy/cloudflare-tunnel.sh):
 *      php /var/www/site/deploy/cloudflare-setup.php
 *
 *  Needs a Cloudflare API token with these permissions:
 *      Account · Cloudflare Tunnel · Edit
 *      Zone · Zone · Read
 *      Zone · DNS · Edit
 *      Zone · Zone Settings · Edit
 *  The token is only used for this run and is not saved.
 * =============================================================================
 */

const API = 'https://api.cloudflare.com/client/v4';

function out(string $msg, string $icon = '✔', string $color = '1;92'): void
{
    fwrite(STDOUT, " \033[{$color}m{$icon}\033[0m {$msg}\n");
}

function fail(string $msg): never
{
    out($msg, '✘', '1;31');
    exit(1);
}

function ask(string $question, ?string $default = null): string
{
    fwrite(STDOUT, $question.($default ? " [{$default}]" : '').': ');
    $answer = trim((string) fgets(STDIN));

    return $answer !== '' ? $answer : (string) $default;
}

function cf(string $method, string $path, ?array $body = null, bool $fatal = true): array
{
    global $apiToken;
    $ch = curl_init(API.$path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$apiToken, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        fail('Could not reach the Cloudflare API: '.curl_error($ch));
    }
    $json = json_decode($raw, true) ?: [];
    if (! ($json['success'] ?? false)) {
        if (! $fatal) {
            return $json;
        }
        $errors = collect_errors($json);
        fail("Cloudflare API error on {$method} {$path}: {$errors}");
    }

    return $json;
}

function collect_errors(array $json): string
{
    $errors = array_map(fn ($e) => ($e['code'] ?? '').' '.($e['message'] ?? ''), $json['errors'] ?? []);

    return $errors ? implode('; ', $errors) : 'unknown error';
}

// -----------------------------------------------------------------------------
// 1. Tunnel ID + account ID from the installed tunnel token
// -----------------------------------------------------------------------------
$tunnelToken = $argv[2] ?? null;
if (! $tunnelToken) {
    foreach (['/etc/systemd/system/cloudflared.service', '/lib/systemd/system/cloudflared.service'] as $unit) {
        if (is_file($unit) && preg_match('/--token\s+(\S+)/', file_get_contents($unit), $m)) {
            $tunnelToken = $m[1];
            break;
        }
    }
}
if (! $tunnelToken) {
    fail('No tunnel found. Run deploy/cloudflare-tunnel.sh first.');
}
$tunnel = json_decode(base64_decode($tunnelToken), true);
$accountId = $tunnel['a'] ?? null;
$tunnelId = $tunnel['t'] ?? null;
if (! $accountId || ! $tunnelId) {
    fail('Could not read the tunnel token.');
}
out("Tunnel {$tunnelId}");

// -----------------------------------------------------------------------------
// 2. Questions
// -----------------------------------------------------------------------------
$domain = $argv[1] ?? ask('Domain', 'nikolajalbrechtsen.com');
$domain = strtolower(preg_replace('#^https?://|/.*$#', '', $domain));

echo "\n Create an API token: https://dash.cloudflare.com/profile/api-tokens → Create Token\n";
echo " → 'Create Custom Token' with these permissions:\n";
echo "     Account · Cloudflare Tunnel · Edit\n";
echo "     Zone · Zone · Read\n";
echo "     Zone · DNS · Edit\n";
echo "     Zone · Zone Settings · Edit\n";
echo "   Zone Resources: Include · Specific zone · {$domain}\n\n";
$apiToken = getenv('CF_API_TOKEN') ?: ask('Paste the API token');
if (strlen($apiToken) < 30) {
    fail('That API token looks too short.');
}

// -----------------------------------------------------------------------------
// 3. Find the zone
// -----------------------------------------------------------------------------
$zones = cf('GET', '/zones?name='.urlencode($domain))['result'];
if (! $zones) {
    fail("Zone {$domain} not found in this Cloudflare account (or the token can't read it).");
}
$zoneId = $zones[0]['id'];
out("Zone {$domain}");

$hostnames = [$domain, 'www.'.$domain];
$target = "{$tunnelId}.cfargotunnel.com";

// -----------------------------------------------------------------------------
// 4. Tunnel routes (ingress)
// -----------------------------------------------------------------------------
$ingress = array_map(fn ($h) => ['hostname' => $h, 'service' => 'http://localhost:80', 'originRequest' => new stdClass], $hostnames);
$ingress[] = ['service' => 'http_status:404'];
cf('PUT', "/accounts/{$accountId}/cfd_tunnel/{$tunnelId}/configurations", ['config' => ['ingress' => $ingress]]);
out('Tunnel routes: '.implode(', ', $hostnames).' → http://localhost:80');

// -----------------------------------------------------------------------------
// 5. DNS: remove old records, add tunnel CNAMEs
// -----------------------------------------------------------------------------
foreach ($hostnames as $host) {
    $records = cf('GET', "/zones/{$zoneId}/dns_records?name=".urlencode($host))['result'];
    $hasTunnelRecord = false;

    foreach ($records as $r) {
        if ($r['type'] === 'CNAME' && $r['content'] === $target) {
            if (! $r['proxied']) {
                cf('PATCH', "/zones/{$zoneId}/dns_records/{$r['id']}", ['proxied' => true]);
            }
            $hasTunnelRecord = true;

            continue;
        }
        if (in_array($r['type'], ['A', 'AAAA', 'CNAME'], true)) {
            cf('DELETE', "/zones/{$zoneId}/dns_records/{$r['id']}");
            out("Removed old {$r['type']} record {$host} → {$r['content']}", '–', '1;33');
        }
    }

    if (! $hasTunnelRecord) {
        cf('POST', "/zones/{$zoneId}/dns_records", [
            'type' => 'CNAME', 'name' => $host, 'content' => $target, 'proxied' => true, 'ttl' => 1,
            'comment' => 'Website via Cloudflare Tunnel',
        ]);
    }
    out("DNS {$host} → tunnel (proxied)");
}

// -----------------------------------------------------------------------------
// 6. Zone settings
// -----------------------------------------------------------------------------
cf('PATCH', "/zones/{$zoneId}/settings/always_use_https", ['value' => 'on']);
out('Always Use HTTPS: on');
if (cf('PATCH', "/zones/{$zoneId}/settings/rocket_loader", ['value' => 'off'], fatal: false)['success'] ?? false) {
    out('Rocket Loader: off');
}

// -----------------------------------------------------------------------------
// 7. Check
// -----------------------------------------------------------------------------
echo "\n Checking https://{$domain} (can take a minute)…\n";
$code = 0;
for ($i = 0; $i < 20; $i++) {
    $ch = curl_init("https://{$domain}/up");
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code === 200) {
        break;
    }
    sleep(5);
}

if ($code === 200) {
    out("https://{$domain} is online 🎉");
} else {
    out("Not reachable yet (HTTP {$code}). DNS can take a few minutes — try https://{$domain} in your browser shortly.", '!', '1;33');
}

echo "\n You can now delete the API token again in Cloudflare (My Profile → API Tokens).\n\n";
