// Utility to seed the first block with a random abstract image and right-split layout

const ABSTRACT_IMAGES = [
  'https://images.unsplash.com/photo-1564951434112-64d74cc2a2d7?ixlib=rb-4.1.0&auto=format&fit=crop&q=80&w=1600',
  'https://images.unsplash.com/photo-1585854467604-cf2080ccef31?ixlib=rb-4.1.0&auto=format&fit=crop&q=80&w=1600',
  'https://images.unsplash.com/photo-1653299832314-5d3dc1e5a83c?ixlib=rb-4.1.0&auto=format&fit=crop&q=80&w=1600',
  'https://images.unsplash.com/photo-1604076913837-52ab5629fba9?ixlib=rb-4.1.0&auto=format&fit=crop&q=80&w=1600',
  'https://images.unsplash.com/photo-1599496507927-9056debd0f0a?ixlib=rb-4.1.0&auto=format&fit=crop&q=80&w=1600',
  'https://images.unsplash.com/photo-1506744038136-46273834b3fb?ixlib=rb-4.1.0&auto=format&fit=crop&q=80&w=1600',
  'https://images.unsplash.com/photo-1500964757637-c85e8a162699?ixlib=rb-4.1.0&auto=format&fit=crop&q=80&w=1600',
  'https://images.unsplash.com/photo-1519681393784-d120267933ba?ixlib=rb-4.1.0&auto=format&fit=crop&q=80&w=1600'
]

function getRandomAbstractImageUrl() {
  const index = Math.floor(Math.random() * ABSTRACT_IMAGES.length)
  return ABSTRACT_IMAGES[index]
}

export function seedFocusedFirstBlockImage(content) {
  if (!content || !Array.isArray(content.properties) || content.properties.length === 0) return
  const firstBlock = content.properties[0]
  if (!firstBlock) return
  firstBlock.image = firstBlock.image || {}
  if (!firstBlock.image.url) {
    firstBlock.image.url = getRandomAbstractImageUrl()
  }
  if (!firstBlock.image.layout) {
    firstBlock.image.layout = 'right-split'
  }
}

export default seedFocusedFirstBlockImage


