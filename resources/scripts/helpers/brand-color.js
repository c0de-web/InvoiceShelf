// How far each --color-primary-* shade (themes.css) moves from the brand colour:
// towards white when positive, towards black when negative; 500 is the colour itself.
const SHADES = {
  50: 0.95,
  100: 0.9,
  200: 0.75,
  300: 0.6,
  400: 0.3,
  500: 0,
  600: -0.15,
  700: -0.3,
  800: -0.45,
  900: -0.55,
  950: -0.7,
}

export function isBrandColor(value) {
  return typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value)
}

export function brandPalette(hex) {
  const rgb = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16))

  return Object.fromEntries(
    Object.entries(SHADES).map(([shade, amount]) => {
      const target = amount > 0 ? 255 : 0
      const mixed = rgb.map((c) => Math.round(c + (target - c) * Math.abs(amount)))

      return [shade, `rgb(${mixed.join(', ')})`]
    })
  )
}

// Recolours the whole app; without a valid colour the themes.css defaults apply again.
export function applyBrandColor(hex) {
  const style = document.documentElement.style

  if (!isBrandColor(hex)) {
    Object.keys(SHADES).forEach((shade) =>
      style.removeProperty(`--color-primary-${shade}`)
    )
    return
  }

  Object.entries(brandPalette(hex)).forEach(([shade, color]) =>
    style.setProperty(`--color-primary-${shade}`, color)
  )
}
