<script setup lang="ts">
import type {
  PublicDesignerIdentity,
  PublicDesignerWork,
} from '~/types/public-designer-profile'

const props = defineProps<{
  identity: PublicDesignerIdentity
  work: PublicDesignerWork
}>()

const avatarFailed = ref(false)
const coverFailed = ref(false)

const initials = computed(() => {
  const words = props.identity.display_name.trim().split(/\s+/u).filter(Boolean)
  return words.slice(0, 2).map(word => Array.from(word)[0]).join('').toUpperCase() || 'YM'
})

const coverSource = computed(() => {
  if (!props.work.cover_media) return null
  return props.work.cover_media.kind === 'video'
    ? props.work.cover_media.poster_url
    : props.work.cover_media.content_url
})

const coverClass = computed(() =>
  props.work.cover_presentation.display_mode === 'fit' ? 'work-media-fit' : 'work-media-fill',
)

const coverPosition = computed(() => {
  const point = props.work.cover_presentation.focal_point
  return `${point.x}% ${point.y}%`
})

const mediaTypeLabel = computed(() => {
  switch (props.work.media_type) {
    case 'image':
      return 'صورة'
    case 'video':
      return 'فيديو'
    case 'gallery':
      return 'معرض'
    default:
      return null
  }
})

const publishedDate = computed(() => props.work.published_at
  ? new Intl.DateTimeFormat('ar-YE-u-nu-latn', {
      dateStyle: 'medium',
      timeZone: 'Asia/Aden',
    }).format(new Date(props.work.published_at))
  : null)
</script>

<template>
  <article class="public-work-card">
    <header class="public-work-author">
      <div class="public-work-avatar">
        <img
          v-if="identity.avatar_url && !avatarFailed"
          :src="identity.avatar_url"
          :alt="`الصورة الشخصية لـ ${identity.display_name}`"
          @error="avatarFailed = true"
        >
        <span v-else aria-hidden="true">{{ initials }}</span>
      </div>
      <div class="min-w-0">
        <p dir="auto" class="public-work-author-name">{{ identity.display_name }}</p>
        <p v-if="publishedDate" class="public-work-date">
          نُشر في <bdi dir="ltr">{{ publishedDate }}</bdi>
        </p>
      </div>
    </header>

    <div class="public-work-copy">
      <h3 dir="auto" class="public-work-title">{{ work.title }}</h3>
      <p v-if="work.summary" dir="auto" class="public-work-summary">{{ work.summary }}</p>
    </div>

    <div class="public-work-media">
      <img
        v-if="coverSource && !coverFailed"
        :src="coverSource"
        :alt="`غلاف العمل: ${work.title}`"
        class="public-work-media-image"
        :class="coverClass"
        :style="{ objectPosition: coverPosition }"
        @error="coverFailed = true"
      >
      <div v-else class="public-work-media-fallback" aria-hidden="true">
        <span />
      </div>
    </div>

    <footer v-if="work.category || work.tags.length || mediaTypeLabel" class="public-work-footer">
      <bdi v-if="work.category" dir="auto" class="public-work-category">
        {{ work.category.name_ar || work.category.name_en }}
      </bdi>
      <bdi v-for="tag in work.tags" :key="tag.slug" dir="auto" class="public-work-tag">
        {{ tag.name_ar || tag.name_en }}
      </bdi>
      <span v-if="mediaTypeLabel" class="public-work-media-type">{{ mediaTypeLabel }}</span>
    </footer>
  </article>
</template>

<style scoped>
.public-work-card {
  position: relative;
  min-width: 0;
  margin-bottom: 16px;
  overflow: hidden;
  border: 1px solid var(--ym-border);
  border-radius: 16px;
  background: var(--ym-surface);
  box-shadow: var(--ym-shadow);
  transition: border-color 180ms ease, box-shadow 180ms ease;
}

.public-work-card::before {
  position: absolute;
  inset-block: 18px auto;
  inset-inline-start: 0;
  z-index: 1;
  width: 3px;
  height: 38px;
  border-radius: 999px;
  background: var(--ym-red);
  content: "";
}

.public-work-card:hover {
  border-color: rgba(226, 29, 29, 0.34);
  box-shadow: 0 14px 34px rgba(17, 17, 17, 0.1);
}

.public-work-card:last-child {
  margin-bottom: 0;
}

.public-work-author {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 11px;
  padding: 16px 20px 10px;
}

.public-work-avatar {
  display: flex;
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  border-radius: 50%;
  border: 2px solid rgba(226, 29, 29, 0.7);
  background: #111;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}

.public-work-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.public-work-author-name {
  margin: 0;
  color: var(--ym-charcoal);
  font-size: 15px;
  font-weight: 700;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.public-work-date {
  margin: 2px 0 0;
  color: var(--ym-muted);
  font-size: 12px;
  line-height: 1.4;
}

.public-work-copy {
  padding: 8px 20px 18px;
}

.public-work-title {
  margin: 0;
  color: var(--ym-charcoal);
  font-size: 21px;
  font-weight: 700;
  line-height: 1.5;
  overflow-wrap: anywhere;
}

.public-work-summary {
  margin: 7px 0 0;
  color: #4b4f57;
  font-size: 16px;
  line-height: 1.75;
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.public-work-media {
  width: 100%;
  max-height: 680px;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  border-block: 1px solid var(--ym-border);
  background: #f1f2f4;
}

.public-work-media-image {
  display: block;
  width: 100%;
  height: 100%;
}

.work-media-fill {
  object-fit: cover;
}

.work-media-fit {
  object-fit: contain;
  background: var(--ym-feature-dark);
}

.public-work-media-fallback {
  display: grid;
  width: 100%;
  height: 100%;
  place-items: center;
  background:
    radial-gradient(circle at 50% 50%, rgba(226, 29, 29, 0.035), transparent 13rem),
    #f3f4f6;
}

.public-work-media-fallback span {
  width: 44px;
  height: 3px;
  border-radius: 999px;
  background: var(--ym-red);
}

.public-work-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 14px 20px 18px;
  background: var(--ym-surface-soft);
}

.public-work-category,
.public-work-tag,
.public-work-media-type {
  max-width: 100%;
  padding: 5px 9px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.public-work-category {
  border: 1px solid rgba(226, 29, 29, 0.24);
  background: var(--ym-red-soft);
  color: var(--ym-red);
}

.public-work-tag {
  border: 1px solid var(--ym-border-strong);
  background: #f5f5f6;
  color: var(--ym-charcoal);
}

.public-work-media-type {
  background: var(--ym-red-soft);
  color: var(--ym-red);
}

@media (max-width: 899px) {
  .public-work-card {
    width: 100%;
    max-width: 100%;
    margin-bottom: 12px;
    border-radius: 10px;
    box-sizing: border-box;
  }

  .public-work-author {
    padding: 14px 16px 9px;
  }

  .public-work-copy {
    padding: 7px 16px 16px;
  }

  .public-work-media {
    max-height: none;
    aspect-ratio: 4 / 3;
  }

  .public-work-footer {
    padding: 12px 16px 16px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .public-work-card {
    transition: border-color 100ms linear;
  }

}
</style>
