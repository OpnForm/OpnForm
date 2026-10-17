#!/usr/bin/env bash
set -euo pipefail

# Build only: never publishes a layer, changes Vapor config or deploys.
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output_dir="${1:-$repo_root/output/pdf-pango-layer}"
mkdir -p "$output_dir"
output_dir="$(cd "$output_dir" && pwd)"
image='amazonlinux:2023@sha256:8ed3c0a996841537f75607e7d1de2114d8150391f75792e8da9268738547e73f'

docker run --rm -i --platform linux/arm64 \
  -v "$repo_root/api/resources/fonts/noto-devanagari:/fonts:ro" \
  -v "$output_dir:/build" "$image" bash -s <<'BUILD'
set -euo pipefail
dnf install -y pango fontconfig zip findutils >/build/package-install.log 2>&1
mkdir -p /build/layer/bin /build/layer/lib /build/layer/share/licenses /build/qa
cp -L /usr/bin/pango-view /build/layer/bin/
ldd /usr/bin/pango-view > /build/ldd-build.txt
awk '/=> \/|^\s*\// { for(i=1;i<=NF;i++) if ($i ~ /^\//) print $i }' /build/ldd-build.txt > /build/library-paths.txt
: > /build/packages.txt
for lib in $(cat /build/library-paths.txt); do
  case "$(basename "$lib")" in
    libc.so.*|libm.so.*|libdl.so.*|libpthread.so.*|librt.so.*|ld-linux-*) continue ;;
  esac
  cp -L "$lib" /build/layer/lib/
  rpm -qf "$lib" >> /build/packages.txt
done
rpm -q pango fontconfig >> /build/packages.txt
sort -u /build/packages.txt -o /build/packages.txt
while read -r package; do
  mkdir -p "/build/layer/share/licenses/$package"
  rpm -ql "$package" | while read -r license; do
    if [[ -f "$license" && "$license" == /usr/share/licenses/* ]]; then
      cp "$license" "/build/layer/share/licenses/$package/"
    fi
  done
done < /build/packages.txt
pango-view --version > /build/pango-version.txt
rpm -q glibc > /build/base-runtime.txt
printf '%s\n' 'मराठी भाषा: प्रशिक्षण अर्ज, क्ष आणि ज्ञ' > /build/qa/input.txt
printf '%s\n' '<?xml version="1.0"?><fontconfig><dir>/fonts</dir><cachedir>/tmp/fontcache</cachedir></fontconfig>' > /build/qa/fonts.conf
FONTCONFIG_FILE=/build/qa/fonts.conf PANGOCAIRO_BACKEND=fc LD_LIBRARY_PATH=/build/layer/lib \
  /build/layer/bin/pango-view --no-display --dpi=72 --margin=0 --font='Noto Sans Devanagari 14' \
  --width=400 --output=/build/qa/marathi-al2023-arm64.pdf /build/qa/input.txt
(cd /build/layer && find bin lib share -type f -print0 | sort -z | xargs -0 sha256sum) > /build/SHA256SUMS
(cd /build/layer && zip -X -q -r /build/opnform-pdf-pango-al2023-arm64.zip bin lib share)
BUILD

# Verify the packaged executable in a fresh base image with no installed packages.
docker run --rm --platform linux/arm64 -e LD_LIBRARY_PATH=/opt/lib \
  -e PANGOCAIRO_BACKEND=fc -e FONTCONFIG_FILE=/build/qa/fonts.conf \
  -v "$output_dir/layer:/opt:ro" -v "$output_dir:/build" \
  -v "$repo_root/api/resources/fonts/noto-devanagari:/fonts:ro" "$image" \
  bash -c 'ldd /opt/bin/pango-view > /build/ldd-packaged.txt; ! grep -q "not found" /build/ldd-packaged.txt; /opt/bin/pango-view --no-display --dpi=72 --margin=0 --font="Noto Sans Devanagari 14" --width=400 --output=/build/qa/marathi-packaged.pdf /build/qa/input.txt'

docker image inspect "$image" --format '{{json .RepoDigests}} {{.Os}}/{{.Architecture}}' > "$output_dir/container-image.txt"
shasum -a 256 "$output_dir/opnform-pdf-pango-al2023-arm64.zip" > "$output_dir/ZIP-SHA256.txt"
printf 'Layer prepared at %s\n' "$output_dir"
