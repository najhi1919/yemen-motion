<template>
  <div class="min-h-screen bg-[#FCFCFC] text-[#111111]" dir="rtl">
    <header class="border-b border-neutral-200 bg-white">
      <div class="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-3 px-3 min-[481px]:gap-4 min-[481px]:px-4 sm:px-6 lg:px-8">
        <div class="flex min-w-0 shrink-0 items-center gap-2 min-[481px]:gap-3">
          <NuxtLink
            to="/designer"
            class="flex min-h-11 shrink-0 items-center gap-2 rounded-xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
            aria-label="العودة إلى مساحة المصمم"
          >
            <img src="/logo.svg" alt="" class="h-10 w-10 shrink-0 object-contain">
            <img src="/name.svg" alt="Yemen Motion" class="hidden h-7 w-auto max-w-36 object-contain md:block">
          </NuxtLink>
          <div class="shrink-0 rounded-full bg-red-50 px-3 py-2 text-sm font-bold text-[#C91414]">
            مساحة المصمم
          </div>
        </div>

        <div class="flex min-w-0 items-center justify-end gap-2 sm:gap-3">
          <span class="hidden min-w-0 max-w-24 text-[15px] font-semibold text-neutral-700 min-[481px]:block sm:max-w-32 md:max-w-48">
            <bdi dir="auto" class="block truncate text-start">
              {{ authStore.user?.name || 'مصمم Yemen Motion' }}
            </bdi>
          </span>
          <button
            type="button"
            class="min-h-11 shrink-0 rounded-xl border border-[#E21D1D]/35 bg-transparent px-3 text-sm font-bold text-[#B81414] transition hover:border-[#E21D1D] hover:bg-[#E21D1D] hover:text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none sm:px-4"
            :disabled="authStore.isLoading"
            @click="logout"
          >
            تسجيل الخروج
          </button>
        </div>
      </div>
    </header>

    <nav
      aria-label="تنقل مساحة المصمم"
      class="border-b border-[rgba(17,17,17,0.09)] bg-white px-4 sm:px-6"
    >
      <div class="mx-auto grid min-h-11 max-w-7xl grid-cols-2 sm:flex sm:w-fit sm:gap-1">
        <NuxtLink
          to="/designer"
          exact-active-class="!border-[#E21D1D] !text-[#C91414]"
          class="inline-flex min-h-11 items-center justify-center border-b-2 border-transparent px-5 text-sm font-bold text-[#666666] transition hover:text-[#151515] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-red-200 motion-reduce:transition-none"
        >
          الملف
        </NuxtLink>
        <NuxtLink
          to="/designer/works"
          active-class="!border-[#E21D1D] !text-[#C91414]"
          class="inline-flex min-h-11 items-center justify-center border-b-2 border-transparent px-5 text-sm font-bold text-[#666666] transition hover:text-[#151515] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-red-200 motion-reduce:transition-none"
        >
          الأعمال
        </NuxtLink>
      </div>
    </nav>

    <main class="min-h-[calc(100vh-4rem)]">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
const authStore = useAuthStore()

async function logout(): Promise<void> {
  await authStore.logout()
  await navigateTo('/auth/login')
}
</script>
