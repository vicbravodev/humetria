#!/usr/bin/env bash
# Renders one film to public/video: mp4, webm and the poster. Usage: render.sh <folder> <public-name> [poster-second] [light]
# With `light` it renders the light-theme variant (same film, light design tokens) as <public-name>-light.
# Needs Docker (there is no ffmpeg here). Example: render.sh attendance humetria-attendance 4
set -euo pipefail
export HYPERFRAMES_NO_TELEMETRY=1
folder=$1; name=$2; poster=${3:-8}; theme=${4:-dark}
here=$(cd "$(dirname "$0")" && pwd)
public="$here/../../public/video"
source_dir="$here/$folder"

if [ "$theme" = "light" ]; then
  name="$name-light"
  source_dir=$(mktemp -d)/$folder
  mkdir -p "$source_dir"
  (cd "$here/$folder" && tar --exclude=out --exclude=.hyperframes -cf - .) | tar -xf - -C "$source_dir"
  # The light tokens of the design system (resources/css/app.css, :root) in place of the dark ones.
  python3 - "$source_dir/index.html" <<'PY'
import re, sys
path = sys.argv[1]
html = open(path).read()
light = """:root {
        --bg: #fbfdfc; --card: #ffffff; --muted: #f1f5f4; --accent: #e5f1ee; --border: #dce5e2;
        --fg: #14211f; --muted-fg: #56635f; --primary: #0b7a6f; --primary-fg: #ffffff;
        --warn: #a47f2c; --warn-bg: #f6eedc; --warn-fg: #7a5714;
      }"""
html, count = re.subn(r":root \{.*?\}", light, html, count=1, flags=re.S)
assert count == 1, "no :root token block"
open(path, "w").write(html)
PY
fi

cd "$source_dir"
mkdir -p out
npx --yes hyperframes@0.9.30 render --docker -q delivery -o "out/$name.mp4"
npx --yes hyperframes@0.9.30 render --docker --format webm --crf 32 -o "out/$name.webm"
rm -rf out/poster
npx --yes hyperframes@0.9.30 snapshot --at "$poster" --no-end -o out/poster
sips -s format jpeg -s formatOptions 80 "$(ls out/poster/*.png | head -1)" --out "$public/$name.jpg" >/dev/null
cp "out/$name.mp4" "out/$name.webm" "$public/"
ls -la "$public/$name".*
