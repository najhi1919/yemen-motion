const svgCache = new Map<string, Promise<string>>()

export function useInlineSvg() {
  function loadSvg(path: string) {
    if (!svgCache.has(path)) {
      svgCache.set(path, fetch(path).then((response) => response.text()))
    }

    return svgCache.get(path)!
  }

  return { loadSvg }
}
