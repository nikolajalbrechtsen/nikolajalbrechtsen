<div align="center">

# nikolajalbrechtsen.com

**My personal website — portfolio, blog and home-lab playground.**

[🌐 Visit the website](https://nikolajalbrechtsen.com) · [📝 Blog](https://nikolajalbrechtsen.com/blog) · [✉️ Contact](https://nikolajalbrechtsen.com/contact)

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![Filament](https://img.shields.io/badge/Filament-5-FDAE4B)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white)
![Self-hosted](https://img.shields.io/badge/Self--hosted-Proxmox-E57000?logo=proxmox&logoColor=white)
![Cloudflare](https://img.shields.io/badge/Cloudflare-Tunnel-F38020?logo=cloudflare&logoColor=white)

</div>

---

## 👋 About me

<!-- ABOUT:START -->
<!-- This section is updated automatically from https://nikolajalbrechtsen.com/about — edit it in the admin panel, not here. -->

*Syncing from [nikolajalbrechtsen.com/about](https://nikolajalbrechtsen.com/about)…*

<!-- ABOUT:END -->

---

## ✨ The website

A fast, modern site I built and host myself. Everything on it is editable from my own admin panel. No code changes needed for content.

- **Pages built from blocks:** hero banners, feature cards, image + text, stats, FAQ, events and a contact form, all arranged by drag and drop.
- **Blog:** posts with cover images, tags, scheduling and members-only posts.
- **Accounts:** sign up with email, Google or GitHub, with pages that only members can see.
- **Calendar:** events, shown on the site when I make them public.
- **Media library:** every image on the site comes from one place, with uploads straight from my PC.
- **Visitor stats:** unique visitors per day, counted privately (IP addresses are hashed, never stored).
- **Light and dark mode** that follows your system.

## 🛠️ Admin panel

| | |
| --- | --- |
| **Content** | Pages, blog posts, media, calendar, contact messages |
| **Users** | Accounts, roles and permissions |
| **System** | Site settings (name, logo, colors, login options), a VS Code-style file editor, and full VS Code in the browser with a terminal |

## 🏠 How it's hosted

```text
GitHub ──git push──▶ Proxmox LXC container (Debian 13)
                       Nginx · PHP 8.4 · MariaDB · code-server
                              │
                     Cloudflare Tunnel (HTTPS, no open ports)
                              │
                     https://nikolajalbrechtsen.com
```

The site runs in a container on my own Proxmox server. Deploying a change is one command, `update`, which pulls from GitHub, builds and migrates. Cloudflare Tunnel provides HTTPS without opening any ports on my network.

---

<sub>The **About me** section above is synced automatically from the website by a [GitHub Action](.github/workflows/sync-about.yml).
Setup, deployment and development notes: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).</sub>
