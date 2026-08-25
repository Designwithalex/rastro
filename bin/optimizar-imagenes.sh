#!/usr/bin/env bash
# ============================================================
# optimizar-imagenes.sh — Rastro Fitness
#
# Deja las imágenes del repo en tamaño y peso de producción.
# Es idempotente: se puede volver a correr cuando el cliente
# manda fotos nuevas y solo toca lo que hace falta.
#
#   1. Guarda una copia intacta en .originales-img/ (ignorada por git).
#   2. Redimensiona al lado largo máximo de cada familia de imagen.
#   3. Genera un .webp al lado de cada archivo, con el mismo nombre.
#
# Requiere: sips (macOS) y cwebp (brew install webp).
# Uso: bash bin/optimizar-imagenes.sh
# ============================================================
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMG="$RAIZ/assets/img"
ORIG="$RAIZ/.originales-img"

command -v sips  >/dev/null || { echo "Falta sips (macOS)."; exit 1; }
command -v cwebp >/dev/null || { echo "Falta cwebp: brew install webp"; exit 1; }

# Copia de respaldo, una sola vez. No se versiona.
if [ ! -d "$ORIG" ]; then
  echo "→ Guardando originales en .originales-img/"
  mkdir -p "$ORIG"
  cp -R "$IMG/" "$ORIG/"
fi

lado_largo() {
  local w h
  w=$(sips -g pixelWidth  "$1" | awk '/pixelWidth/{print $2}')
  h=$(sips -g pixelHeight "$1" | awk '/pixelHeight/{print $2}')
  [ "$w" -gt "$h" ] && echo "$w" || echo "$h"
}

# redimensionar <archivo> <lado_maximo>
redimensionar() {
  local f="$1" max="$2"
  if [ "$(lado_largo "$f")" -gt "$max" ]; then
    sips -Z "$max" "$f" >/dev/null
  fi
}

# webp <archivo> <calidad> [extra...]
webp() {
  local f="$1" q="$2"; shift 2
  local destino="${f%.*}.webp"
  cwebp -quiet -q "$q" -metadata none "$@" "$f" -o "$destino"
}

echo "→ Fotos de producto (lado largo 1000, JPEG + WebP)"
for f in "$IMG"/productos/*.jpg; do
  redimensionar "$f" 1000
  sips -s format jpeg -s formatOptions 78 "$f" --out "$f" >/dev/null
  webp "$f" 80
done

echo "→ Recortes con transparencia (lado largo 1000, solo WebP)"
# El PNG original pesa ~1 MB y no se versiona: cada recorte tiene su
# gemelo .jpg en la carpeta de arriba, que hace de respaldo si el
# navegador no soporta WebP.
for f in "$IMG"/productos/png/*.png; do
  [ -e "$f" ] || continue
  redimensionar "$f" 1000
  webp "$f" 80 -alpha_q 90
  rm -f "$f"
done

echo "→ Fotos de ambiente (lado largo 1600, JPEG + WebP)"
for f in "$IMG"/ambiente/*.jpg; do
  redimensionar "$f" 1600
  sips -s format jpeg -s formatOptions 75 "$f" --out "$f" >/dev/null
  webp "$f" 78
done

echo "→ Marca (lado largo 600, PNG con transparencia + WebP)"
for f in "$IMG"/marca/*.png; do
  redimensionar "$f" 600
  webp "$f" 88 -alpha_q 100
done

echo "→ Favicon e íconos: ya pesan lo que tienen que pesar, no se tocan."
echo
echo "Total versionado en assets/img: $(du -sh "$IMG" | cut -f1)"
