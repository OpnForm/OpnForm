<template>
  <main class="split-welcome">
    <section class="instance" aria-labelledby="access-title">
      <div class="instance-access">
        <div class="instance-address">
          <span class="instance-symbol"><img v-if="logoUrl !== '/img/logo.svg'" :src="logoUrl" alt="" /><UIcon v-else name="i-heroicons-server-stack" /></span>
          <span class="instance-identity"><span v-if="name !== 'OpnForm'" class="instance-name">{{ name }}</span><span>{{ instanceHost }}</span></span>
        </div>
        <h1 id="access-title">Your team’s<br />form workspace.</h1>
        <p class="instance-description">Sign in to create forms and manage<br class="desktop-break" /> responses on this instance.</p>
        <UButton :to="{ name: 'login' }" trailing-icon="i-heroicons-arrow-right" label="Sign in" class="instance-sign-in" />
        <NuxtLink :to="{ name: 'templates' }" class="instance-templates">Browse templates</NuxtLink>
        <p class="instance-note"><UIcon name="i-heroicons-information-circle" /><span>Looking for a form? Use the link shared with you.</span></p>
      </div>
      <footer class="instance-footer">
        <p>Hosted independently · Powered by <a :href="cloudUrl" target="_blank" rel="noopener noreferrer">OpnForm</a></p>
        <div v-if="privacyPolicyUrl || termsUrl" class="instance-legal">
          <a v-if="privacyPolicyUrl" :href="privacyPolicyUrl">Privacy policy</a>
          <a v-if="termsUrl" :href="termsUrl">Terms</a>
        </div>
      </footer>
    </section>

    <section class="studio" aria-labelledby="studio-title">
      <img src="/img/self-hosted/studio-background.webp" alt="" class="studio-background" width="1122" height="1402" decoding="async" />
      <a :href="cloudUrl" target="_blank" rel="noopener noreferrer" class="studio-brand">
        <img src="/img/logo.svg" alt="" />
        <span>OpnForm</span>
      </a>
      <div class="studio-copy">
        <h2 id="studio-title">Your forms.<br />Your way.</h2>
        <p>The open-source form builder.</p>
      </div>
      <div class="studio-scene" aria-hidden="true">
        <div class="studio-cluster">
          <img
            src="/img/self-hosted/feedback-card.webp"
            srcset="/img/self-hosted/feedback-card-640.webp 640w, /img/self-hosted/feedback-card.webp 950w"
            sizes="(max-width: 600px) 62vw, (max-width: 960px) 350px, 28vw"
            width="950"
            height="1253"
            alt=""
            class="studio-card feedback-card"
            decoding="async"
          />
          <img
            src="/img/self-hosted/project-card.webp"
            srcset="/img/self-hosted/project-card-640.webp 640w, /img/self-hosted/project-card.webp 948w"
            sizes="(max-width: 600px) 74vw, (max-width: 960px) 420px, 34vw"
            width="948"
            height="1173"
            alt=""
            class="studio-card project-card"
            decoding="async"
            fetchpriority="high"
          />
        </div>
      </div>
      <div class="studio-bottom">
        <a :href="cloudUrl" target="_blank" rel="noopener noreferrer" class="studio-discover">
          Build your own forms with OpnForm <UIcon name="i-heroicons-arrow-up-right" />
        </a>
      </div>
    </section>
  </main>
</template>

<script setup>
const instanceHost = useRequestURL().host
const { name, logoUrl, privacyPolicyUrl, termsUrl } = useSelfHostedPresentation()
const cloudUrl = 'https://opnform.com?utm_source=self_hosted&utm_medium=instance&utm_campaign=welcome_split'
</script>

<style scoped>
.split-welcome {
  display: grid;
  grid-template-columns: minmax(0, 1.402fr) minmax(0, 1fr);
  min-height: 100svh;
  background: #fff;
  color: #10131b;
}

.studio {
  grid-column: 1;
  grid-row: 1;
  position: relative;
  isolation: isolate;
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: max(100svh, 760px);
  overflow: hidden;
  padding: clamp(28px, 4svh, 44px) clamp(32px, 5.38vw, 96px) clamp(28px, 4.5svh, 48px);
  color: #fff;
  background: #3261ff;
}

.studio-background {
  position: absolute;
  z-index: -1;
  width: 100%;
  height: 100%;
  inset: 0;
  object-fit: cover;
  pointer-events: none;
  filter: hue-rotate(-7deg);
}

.studio-brand {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  gap: 12px;
  font-size: 27px;
  font-weight: 600;
  letter-spacing: -.8px;
  color: #fff;
}

.studio-brand img { width: 38px; height: 38px; filter: brightness(0) invert(1); }
.studio-copy { margin-top: clamp(28px, 5.5svh, 58px); }
.studio-copy h2 {
  color: #fff;
  font-size: clamp(50px, min(5.5vw, 8svh), 86px);
  font-weight: 500;
  letter-spacing: -2.8px;
  line-height: 1.04;
}
.studio-copy p {
  margin-top: 12px;
  font-size: clamp(18px, min(2.17vw, 3.2svh), 34px);
  line-height: 1.4;
  letter-spacing: -.6px;
}

.studio-scene {
  position: relative;
  flex: 1;
  min-height: 300px;
  margin-top: 32px;
  margin-bottom: 40px;
  pointer-events: none;
}
.studio-cluster { position: absolute; top: 0; left: 50%; height: 100%; width: auto; max-width: 100%; aspect-ratio: 1.25; transform: translateX(-50%); }
.studio-card { position: absolute; object-fit: contain; }
.feedback-card {
  --card-angle: -9deg;
  height: 83%;
  width: auto;
  max-width: 67%;
  left: 6%;
  top: 8%;
  transform: rotate(var(--card-angle));
}
.project-card {
  --card-angle: 7deg;
  height: 96%;
  width: auto;
  max-width: 79%;
  right: 2%;
  bottom: 1%;
  transform: rotate(var(--card-angle));
}
.studio-bottom { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-top: auto; }
.studio-discover {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  gap: 10px;
  padding-bottom: 10px;
  border-bottom: 1px solid #ffffff99;
  font-size: clamp(13px, 1.08vw, 16px);
  color: #fff;
}
.studio-discover .iconify { flex-shrink: 0; width: 16px; height: 16px; }

.instance {
  grid-column: 2;
  grid-row: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  padding: clamp(32px, 6svh, 72px) clamp(32px, 5.5vw, 88px) clamp(28px, 5svh, 62px) clamp(40px, 8.07vw, 120px);
}
.instance-access { width: 100%; max-width: 480px; margin-block: auto; }
.instance-address { display: flex; align-items: center; gap: 20px; font-size: clamp(16px, 1.28vw, 19px); font-weight: 500; color: #788296; }
.instance-symbol { flex-shrink: 0; display: flex; align-items: center; justify-content: center; width: 54px; height: 54px; border-radius: 14px; background: #f1f2f6; color: #111827; }
.instance-symbol .iconify { width: 28px; height: 28px; }
.instance-symbol img { width: 30px; height: 30px; object-fit: contain; }
.instance-identity { display: flex; min-width: 0; flex-direction: column; gap: 3px; overflow-wrap: anywhere; }
.instance-name { color: #10131b; }
.instance h1 { color: #10131b; margin-top: clamp(24px, 3.4svh, 36px); font-size: clamp(34px, min(3.37vw, 4.8svh), 53px); line-height: 1.23; font-weight: 600; letter-spacing: -2px; }
.instance-description { margin-top: 24px; font-size: clamp(16px, 1.4vw, 20px); line-height: 1.5; color: #788296; letter-spacing: -.2px; }
.instance-sign-in { display: flex; justify-content: center; gap: 16px; width: 100%; min-height: clamp(52px, 6svh, 63px); margin-top: clamp(24px, 3svh, 32px); border-radius: 10px; background: #2674ff; color: #fff; font-size: 20px; font-weight: 500; }
.instance-sign-in:hover { background: #1761ed; }
.instance-sign-in :deep(.iconify) { width: 25px; height: 25px; }
.instance-templates { display: inline-block; margin-top: 22px; font-size: 18px; font-weight: 500; color: #2674ff; }
.instance-templates:hover { text-decoration: underline; }
.instance-note { display: flex; gap: 16px; align-items: flex-start; margin-top: clamp(32px, 4.5svh, 48px); padding-top: clamp(24px, 3.8svh, 40px); border-top: 1px solid #d8dfea; font-size: 14px; line-height: 1.7; color: #7b869b; }
.instance-note .iconify { flex-shrink: 0; width: 24px; height: 24px; }
.instance-footer { width: 100%; padding-top: 40px; color: #8791a5; font-size: 13px; text-align: center; line-height: 1.6; }
.instance-footer a:hover { text-decoration: underline; }
.instance-legal { display: flex; justify-content: center; flex-wrap: wrap; gap: 8px 18px; margin-top: 8px; }
a:focus-visible { outline: 2px solid currentColor; outline-offset: 6px; }

@media (min-width: 1920px) {
  .studio-scene { max-height: 640px; width: min(100%, 880px); align-self: center; }
  .studio-copy h2 { font-size: 88px; }
}
@media (min-width: 961px) and (max-height: 800px) {
  .studio { min-height: max(100svh, 700px); }
  .studio-copy { margin-top: 28px; }
  .studio-scene { min-height: 280px; margin-top: 24px; margin-bottom: 32px; }
}
@media (max-width: 1200px) {
  .instance { padding-inline: 4vw; }
  .desktop-break { display: none; }
  .studio-copy h2 { letter-spacing: -1.7px; }
}
@media (max-width: 960px) {
  .split-welcome { grid-template-columns: minmax(0, 1fr); }
  .instance { grid-column: 1; grid-row: 1; align-items: center; padding: 44px 28px 28px; }
  .instance-address { font-size: 16px; gap: 14px; }
  .instance h1 { font-size: clamp(32px, 10vw, 44px); letter-spacing: -1.5px; margin-top: 26px; }
  .instance-description { font-size: 18px; }
  .instance-sign-in { min-height: 56px; }
  .instance-note { margin-top: 32px; padding-top: 26px; }
  .instance-footer { max-width: 480px; padding-top: 40px; font-size: 12px; }
  .studio { grid-column: 1; grid-row: 2; min-height: 0; padding: 28px; }
  .studio-brand { font-size: 24px; }
  .studio-brand img { width: 32px; height: 32px; }
  .studio-copy { margin-top: 32px; }
  .studio-copy h2 { font-size: clamp(36px, 8.7vw, 58px); line-height: 1.08; letter-spacing: -1.5px; }
  .studio-copy p { font-size: clamp(16px, 4vw, 24px); margin-top: 15px; }
  .studio-scene { flex: none; width: 100%; max-width: 620px; height: clamp(280px, 86vw, 470px); min-height: 0; align-self: center; margin-block: 28px 40px; }
  .studio-discover { font-size: 13px; }
}
@media (max-width: 359px) {
  .studio, .instance { padding-inline: 24px; }
  .studio-copy h2 { font-size: 34px; }
  .instance h1 { font-size: 32px; }
  .instance-description { font-size: 16px; }
  .studio-discover { font-size: 12px; }
  .instance-note { gap: 12px; font-size: 13px; }
}
</style>
