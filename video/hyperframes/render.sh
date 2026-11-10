#!/usr/bin/env bash
# Renders one film to public/video: mp4, webm and the poster. Usage: render.sh <folder> <public-name> [poster-second]
# Needs Docker (there is no ffmpeg here). Example: render.sh attendance humetria-attendance 5.6
set -euo pipefail
export HYPERFRAMES_NO_TELEMETRY=1
folder=$1; name=$2; poster=${3:-8}
here=$(cd "$(dirname "$0")" && pwd)
public="$here/../../public/video"
cd "$here/$folder"
mkdir -p out
npx --yes hyperframes@0.9.30 render --docker -q delivery -o "out/$name.mp4"
npx --yes hyperframes@0.9.30 render --docker --format webm --crf 32 -o "out/$name.webm"
rm -rf out/poster
npx --yes hyperframes@0.9.30 snapshot --at "$poster" --no-end -o out/poster
sips -s format jpeg -s formatOptions 80 "$(ls out/poster/*.png | head -1)" --out "$public/$name.jpg" >/dev/null
cp "out/$name.mp4" "out/$name.webm" "$public/"
ls -la "$public/$name".*
