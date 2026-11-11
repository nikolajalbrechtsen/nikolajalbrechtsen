"""
Replaces the About section of README.md (between the ABOUT markers)
with the live About page from the website (/about.md).

Usage: python3 .github/scripts/sync_about.py <site-url> [readme-path]
Exits 0 in all normal cases; prints whether the README changed.
"""

import re
import sys
import urllib.request

START = "<!-- ABOUT:START -->"
END = "<!-- ABOUT:END -->"


def main() -> int:
    site = sys.argv[1].rstrip("/")
    readme_path = sys.argv[2] if len(sys.argv) > 2 else "README.md"

    req = urllib.request.Request(
        f"{site}/about.md",
        headers={"User-Agent": "readme-sync (GitHub Actions)", "Accept": "text/markdown"},
    )
    with urllib.request.urlopen(req, timeout=30) as resp:
        if resp.status != 200:
            print(f"Website returned HTTP {resp.status}, leaving README unchanged.")
            return 1
        about = resp.read().decode("utf-8").strip()

    if not about:
        print("About page is empty, leaving README unchanged.")
        return 0

    # Keep GitHub from treating "# " in About content as the README title.
    about = re.sub(r"^# ", "## ", about, flags=re.M)

    with open(readme_path, encoding="utf-8") as f:
        readme = f.read()

    if START not in readme or END not in readme:
        print("ABOUT markers not found in README.md — nothing to do.")
        return 1

    section = (
        f"{START}\n"
        f"<!-- This section is updated automatically from {site}/about — edit it in the admin panel, not here. -->\n\n"
        f"{about}\n\n"
        f"{END}"
    )
    updated = re.sub(re.escape(START) + r".*?" + re.escape(END), lambda _: section, readme, flags=re.S)

    if updated == readme:
        print("README already up to date.")
        return 0

    with open(readme_path, "w", encoding="utf-8") as f:
        f.write(updated)
    print("README About section updated.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
