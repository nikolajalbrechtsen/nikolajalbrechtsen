# Website

Laravel 12 website with a custom admin panel (Filament 5), user accounts, social login,
an online VS Code editor and one-command deploys to a Proxmox container.

| URL | What |
| --- | --- |
| `/` | Public website (pages built from blocks in the admin panel) |
| `/blog` | Blog |
| `/login`, `/register` | User accounts (email + Google/GitHub) |
| `/dashboard` | Logged-in user area (members-only content, profile settings) |
| `/admin` | Admin panel |
| `/admin/file-editor` | Monaco (VS Code) editor for every file of the site |
| `/code/` | Full VS Code in the browser (code-server) — super admins only |

---

## 1. Deploy to Proxmox (first time)

### a) Copy the helper script to the Proxmox host

From **PowerShell on your PC** (replace `PROXMOX-IP`):

```powershell
scp deploy/proxmox-helper.sh root@PROXMOX-IP:/root/
```

### b) Run it in the Proxmox host shell

```bash
bash /root/proxmox-helper.sh
```

It works like the community helper scripts: choose **Default** (2 CPU, 2 GB RAM, 10 GB disk, DHCP)
or **Advanced**, enter your admin email, and it creates a Debian 13 container.
Halfway through it shows a **deploy key** with a direct link to GitHub. Add it and
**tick "Allow write access"**, then press ENTER. When it finishes it prints the website URL,
the admin login and the container root password.

<details><summary>Manual alternative (existing Debian container)</summary>

Copy `deploy/install.sh` into any Debian 12/13 container and run `bash install.sh`. It asks the same questions.
</details>

---

## 2. Everyday workflow

```text
edit on your PC  →  git push  →  type `update` in the container terminal
```

* `update` — pulls from GitHub, installs packages / rebuilds CSS+JS only when needed,
  migrates the database, clears caches. The site shows a maintenance page for a few seconds.
* `update --full` — rebuild everything.

**Editing online** (Admin → File editor, or VS Code at `/code/`) changes files directly on the server.
They go live immediately for PHP/Blade files. The next `update` commits them and pushes them to GitHub,
so run `git pull` on your PC afterwards.

* CSS/JS edited online → click **Build CSS/JS** in the File editor (or run `update --full`).
* Config files edited online → click **Clear caches**.

Content (pages, posts, settings, users, uploads) lives in the database / `storage` and is never touched by `update`.

---

## 3. Admin panel guide

| Menu | What you can do |
| --- | --- |
| **Pages** | Build pages with drag-and-drop blocks: Hero, Text, Feature cards, Image + text, Stats, FAQ, Call to action, Latest posts, Contact form, Custom HTML. Choose URL, menu position, homepage, and *Everyone* or *Members only*. Drag rows to reorder the menu. |
| **Blog posts** | Rich-text editor with images, cover image, tags, draft / scheduled / published, members-only. |
| **Messages** | Contact form submissions (badge shows unread). |
| **Users** | Create/edit users, assign roles, deactivate, verify email. |
| **Roles & permissions** | Tick what each role can do. Built in: `super_admin` (everything), `admin`, `editor`, `member` (default for sign-ups). |
| **Settings** | Site name, tagline, logo, favicon, brand color, social links, announcement banner, sign-ups on/off, email verification, Google/GitHub login keys, blog options, analytics code. |
| **File editor** | VS Code-style editor (tabs, syntax highlighting, Ctrl+S) for all project files. |
| **VS Code** | Opens full VS Code (code-server) with terminal, search, git and extensions. |

### Social login

Admin → Settings → *Users & login*. The page shows the exact callback URL to paste into
Google Cloud Console / GitHub OAuth app.

### Email

Password reset, email verification and contact-form notifications need SMTP. Edit `/var/www/site/.env`
in VS Code (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`), then
click **Clear caches**. Until then, mails are written to `storage/logs`.

### HTTPS / domain

Point a reverse proxy (Nginx Proxy Manager, Caddy, Cloudflare Tunnel…) at the container's port 80 and set
`APP_URL=https://your-domain` in `.env`, then **Clear caches**.

---

## 4. Running locally on Windows (optional)

Portable PHP + Composer live in `.tools/` (not committed).

```powershell
.\.tools\php\php.exe artisan serve          # http://127.0.0.1:8000
npm run dev                                  # live CSS/JS reload (second terminal)
.\.tools\php\php.exe artisan test            # run the tests
```

## 5. Where things are

| Path | What |
| --- | --- |
| `app/Filament/` | Admin panel: resources, Settings page, File editor, dashboard widgets |
| `app/Filament/Blocks/PageBlocks.php` | Page block definitions (add new blocks here) |
| `resources/views/blocks/` | How each block looks on the website |
| `resources/views/components/layouts/site.blade.php` | Website header / footer layout |
| `resources/css/app.css` | Tailwind CSS + theme |
| `routes/web.php` | Website routes |
| `deploy/` | `install.sh`, `update.sh`, Nginx config |
