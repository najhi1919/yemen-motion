export interface ProfessionalSuggestion {
  name: string
  keywords?: string[]
  badge?: string
  badgeBackground?: string
  badgeForeground?: string
}

export interface DesignerProfessionalCatalog {
  services: ProfessionalSuggestion[]
  styles: ProfessionalSuggestion[]
  skills: ProfessionalSuggestion[]
  tools: ProfessionalSuggestion[]
  languages: ProfessionalSuggestion[]
}

export interface ProfessionalToolPresentation {
  badge: string
  badgeBackground: string
  badgeForeground: string
}

type ProfessionalCatalogKind = 'photoshop' | 'graphic' | 'motion' | 'uiux' | 'three_d'

const otherSuggestion: ProfessionalSuggestion = { name: 'أخرى' }
const suggestions = (names: string[]): ProfessionalSuggestion[] => names.map(name => ({ name }))

const services: Record<ProfessionalCatalogKind, string[]> = {
  photoshop: ['معالجة وتنقيح الصور', 'الدمج والتلاعب بالصور', 'تصميم الإعلانات الرقمية', 'تصميم منشورات التواصل الاجتماعي', 'إزالة الخلفيات وتحسين الصور'],
  graphic: ['تصميم الشعارات', 'تصميم الهوية البصرية', 'تصميم منشورات التواصل الاجتماعي', 'تصميم الحملات الإعلانية', 'تصميم المطبوعات'],
  motion: ['موشن جرافيك', 'مونتاج الفيديو', 'مقدمات وخواتيم الفيديو', 'تحريك ثنائي الأبعاد', 'المؤثرات البصرية'],
  uiux: ['تصميم واجهات التطبيقات', 'تصميم مواقع الويب', 'تصميم تجربة المستخدم', 'النماذج التفاعلية', 'أنظمة التصميم'],
  three_d: ['النمذجة ثلاثية الأبعاد', 'التحريك ثلاثي الأبعاد', 'الإظهار والرندر', 'تصور المنتجات', 'المؤثرات البصرية ثلاثية الأبعاد'],
}

const styles = ['بسيط (Minimal)', 'حديث (Modern)', 'جريء (Bold)', 'مؤسسي (Corporate)', 'تحريري (Editorial)', 'كلاسيكي (Classic)']

const skills: Record<ProfessionalCatalogKind, string[]> = {
  photoshop: ['معالجة الصور', 'الدمج البصري', 'التكوين البصري', 'نظرية الألوان', 'تصميم الشعارات', 'بناء الهوية البصرية', 'Typography', 'الإخراج الفني'],
  graphic: ['معالجة الصور', 'الدمج البصري', 'التكوين البصري', 'نظرية الألوان', 'تصميم الشعارات', 'بناء الهوية البصرية', 'Typography', 'الإخراج الفني'],
  motion: ['التحريك ثنائي الأبعاد', 'المونتاج', 'تصميم الحركة', 'المؤثرات البصرية', 'تصحيح الألوان', 'Storyboarding', 'Sound Synchronization', 'الإخراج البصري'],
  uiux: ['تصميم واجهات المستخدم', 'تجربة المستخدم', 'النماذج التفاعلية', 'أنظمة التصميم', 'Wireframing', 'User Research', 'Information Architecture', 'Usability Testing'],
  three_d: ['النمذجة ثلاثية الأبعاد', 'التحريك ثلاثي الأبعاد', 'الخامات والإكساء', 'الإضاءة', 'الرندر', 'Compositing', 'Sculpting', 'Product Visualization'],
}

const toolCatalog: ProfessionalSuggestion[] = [
  { name: 'Adobe Photoshop', badge: 'Ps', badgeBackground: '#001E36', badgeForeground: '#31A8FF' },
  { name: 'Adobe Illustrator', badge: 'Ai', badgeBackground: '#330000', badgeForeground: '#FF9A00' },
  { name: 'Adobe After Effects', badge: 'Ae', badgeBackground: '#00005B', badgeForeground: '#9999FF' },
  { name: 'Adobe Premiere Pro', badge: 'Pr', badgeBackground: '#00005B', badgeForeground: '#9999FF' },
  { name: 'Adobe InDesign', badge: 'Id', badgeBackground: '#49021F', badgeForeground: '#FF3366' },
  { name: 'Adobe Lightroom', badge: 'Lr', badgeBackground: '#001E36', badgeForeground: '#31A8FF' },
  { name: 'Adobe Audition', badge: 'Au', badgeBackground: '#00005B', badgeForeground: '#9999FF' },
  { name: 'Figma', badge: 'Fg', badgeBackground: '#1E1E1E', badgeForeground: '#FFFFFF' },
  { name: 'FigJam', badge: 'Fj', badgeBackground: '#FFF3D6', badgeForeground: '#111111' },
  { name: 'Blender', badge: 'Bl', badgeBackground: '#EA7600', badgeForeground: '#FFFFFF' },
  { name: 'DaVinci Resolve', badge: 'DR', badgeBackground: '#171717', badgeForeground: '#FFFFFF' },
  { name: 'Cinema 4D', badge: 'C4D', badgeBackground: '#0B4BA0', badgeForeground: '#FFFFFF' },
  { name: 'Canva', badge: 'Cv', badgeBackground: '#7D2AE8', badgeForeground: '#FFFFFF' },
]

const toolOrder: Record<ProfessionalCatalogKind, string[]> = {
  photoshop: ['Adobe Photoshop', 'Adobe Illustrator', 'Adobe Lightroom', 'Adobe InDesign', 'Adobe After Effects', 'Figma', 'Canva'],
  graphic: ['Adobe Illustrator', 'Adobe Photoshop', 'Adobe InDesign', 'Figma', 'Adobe After Effects', 'Adobe Lightroom', 'Canva'],
  motion: ['Adobe After Effects', 'Adobe Premiere Pro', 'DaVinci Resolve', 'Cinema 4D', 'Blender', 'Adobe Audition', 'Adobe Photoshop'],
  uiux: ['Figma', 'FigJam', 'Adobe Photoshop', 'Adobe Illustrator', 'Adobe After Effects', 'Canva'],
  three_d: ['Blender', 'Cinema 4D', 'Adobe After Effects', 'Adobe Photoshop', 'DaVinci Resolve', 'Adobe Premiere Pro'],
}

const languages: ProfessionalSuggestion[] = [
  { name: 'العربية', badge: 'AR' },
  { name: 'English', badge: 'EN' },
  { name: 'Français', badge: 'FR' },
  { name: 'Deutsch', badge: 'DE' },
  { name: 'Türkçe', badge: 'TR' },
  { name: 'Español', badge: 'ES' },
]

const categoryKeywords: Array<[ProfessionalCatalogKind, string[]]> = [
  ['photoshop', ['فوتوشوب', 'photoshop', 'صور', 'photo', 'image', 'معالجة الصور']],
  ['motion', ['موشن', 'motion', 'تحريك', 'animation', 'مونتاج', 'video', 'فيديو']],
  ['uiux', ['ui', 'ux', 'واجهات', 'تجربة المستخدم', 'figma', 'تطبيقات', 'مواقع']],
  ['three_d', ['3d', 'ثري دي', 'ثلاثي الأبعاد', 'blender', 'cinema 4d']],
  ['graphic', ['جرافيك', 'graphic', 'هوية', 'branding', 'شعارات', 'illustrator']],
]

const normalize = (value: string) => value.trim().replace(/\s+/g, ' ').toLocaleLowerCase('und')

const toolAliases: Record<string, string> = {
  photoshop: 'Adobe Photoshop',
  illustrator: 'Adobe Illustrator',
  'after effects': 'Adobe After Effects',
  'premiere pro': 'Adobe Premiere Pro',
  indesign: 'Adobe InDesign',
  lightroom: 'Adobe Lightroom',
  audition: 'Adobe Audition',
  davinci: 'DaVinci Resolve',
  'davinci resolve': 'DaVinci Resolve',
}

export function resolveProfessionalToolPresentation(name: string): ProfessionalToolPresentation {
  const cleanedName = name.trim().replace(/\s+/g, ' ')
  const normalizedName = normalize(cleanedName)
  const canonicalName = toolAliases[normalizedName] || cleanedName
  const tool = toolCatalog.find(item => normalize(item.name) === normalize(canonicalName))

  if (tool?.badge && tool.badgeBackground && tool.badgeForeground) {
    return {
      badge: tool.badge,
      badgeBackground: tool.badgeBackground,
      badgeForeground: tool.badgeForeground,
    }
  }

  const badge = Array.from(cleanedName)
    .filter(character => !/\s/u.test(character))
    .slice(0, 2)
    .join('')
    .toLocaleUpperCase('und') || '—'

  return { badge, badgeBackground: '#262626', badgeForeground: '#FFFFFF' }
}

function resolveKind(primarySpecialty: string | null | undefined): ProfessionalCatalogKind {
  const value = normalize(primarySpecialty || '')
  return categoryKeywords.find(([, keywords]) => keywords.some(keyword => value.includes(normalize(keyword))))?.[0] || 'graphic'
}

export function resolveDesignerProfessionalCatalog(primarySpecialty: string | null | undefined): DesignerProfessionalCatalog {
  const kind = resolveKind(primarySpecialty)
  const tools = toolOrder[kind].map(name => toolCatalog.find(tool => tool.name === name)!).filter(Boolean)

  return {
    services: [...suggestions(services[kind]), otherSuggestion],
    styles: [...suggestions(styles), otherSuggestion],
    skills: [...suggestions(skills[kind]), otherSuggestion],
    tools: [...tools, otherSuggestion],
    languages: [...languages, otherSuggestion],
  }
}
