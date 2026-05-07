/**
 * Génère les icônes PNG pour la PWA (192×192 et 512×512)
 * depuis le SVG Paddock symbol avec fond coloré.
 */
import { chromium } from 'playwright'
import { readFileSync, writeFileSync } from 'fs'
import { resolve, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const svgPath = resolve(__dirname, 'public/branding/paddock-logo-symbol.svg')
const svgContent = readFileSync(svgPath, 'utf8')

const SIZES = [192, 512]
// Fond : couleur brand Paddock (#1a1a1a) avec symbole doré
const BG = '#111111'

async function generate() {
  const browser = await chromium.launch()
  const page = await browser.newPage()

  for (const size of SIZES) {
    await page.setViewportSize({ width: size, height: size })

    // Proportions SVG : 260×230 → on centre dans un carré avec padding
    const padding = Math.round(size * 0.12)
    const svgSize = size - padding * 2

    const html = `<!DOCTYPE html>
<html>
<head>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    width: ${size}px;
    height: ${size}px;
    background: ${BG};
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  svg {
    width: ${svgSize}px;
    height: ${svgSize}px;
  }
</style>
</head>
<body>
${svgContent}
</body>
</html>`

    await page.setContent(html, { waitUntil: 'networkidle' })
    const buffer = await page.screenshot({ type: 'png', clip: { x: 0, y: 0, width: size, height: size } })

    const outPath = resolve(__dirname, `public/branding/paddock-icon-${size}.png`)
    writeFileSync(outPath, buffer)
    console.log(`✓ ${outPath} (${buffer.length} bytes)`)
  }

  await browser.close()
  console.log('Done.')
}

generate().catch(e => { console.error(e); process.exit(1) })
