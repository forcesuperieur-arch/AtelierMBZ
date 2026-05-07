<template>
  <div>
    <NuxtLayout name="auth">
      <div class="bg-white rounded-xl border border-border-light shadow-card p-8">
        <div class="text-center mb-8">
          <div class="text-4xl mb-3">🔐</div>
          <h1 class="text-xl font-bold text-text-primary">Vérification 2FA</h1>
          <p class="text-sm text-text-secondary mt-1">Entrez le code envoyé sur votre téléphone</p>
        </div>

        <!-- 6 digit inputs -->
        <div class="flex justify-center gap-2 mb-8">
          <input
            v-for="(digit, index) in code"
            :key="index"
            :ref="(el) => { if (el) inputs[index] = el as HTMLInputElement }"
            v-model="code[index]"
            type="text"
            maxlength="1"
            inputmode="numeric"
            class="w-12 h-14 text-center text-xl font-bold rounded-lg border border-border-light bg-body-page text-text-primary focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none transition-all"
            @keydown="(e) => onKeydown(e, index)"
            @input="(e) => onInput(e, index)"
            @paste="onPaste"
          />
        </div>

        <PaddockButton variant="primary" class="w-full justify-center py-3" @click="verify">
          Vérifier
        </PaddockButton>

        <p class="text-center text-xs text-text-muted mt-4">
          Code non reçu ? <button class="text-accent hover:underline">Renvoyer</button>
        </p>
      </div>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

definePageMeta({ layout: false })

const router = useRouter()
const code = ref<string[]>(Array(6).fill(''))
const inputs = ref<HTMLInputElement[]>([])

function onInput(e: Event, index: number) {
  const target = e.target as HTMLInputElement
  const val = target.value.replace(/\D/g, '')
  code.value[index] = val.slice(-1)
  target.value = code.value[index]

  if (code.value[index] && index < 5) {
    inputs.value[index + 1]?.focus()
  }
}

function onKeydown(e: KeyboardEvent, index: number) {
  if (e.key === 'Backspace' && !code.value[index] && index > 0) {
    code.value[index - 1] = ''
    inputs.value[index - 1]?.focus()
  }
  if (e.key === 'ArrowLeft' && index > 0) {
    inputs.value[index - 1]?.focus()
  }
  if (e.key === 'ArrowRight' && index < 5) {
    inputs.value[index + 1]?.focus()
  }
}

function onPaste(e: ClipboardEvent) {
  e.preventDefault()
  const paste = e.clipboardData?.getData('text').replace(/\D/g, '').slice(0, 6) || ''
  for (let i = 0; i < paste.length; i++) {
    code.value[i] = paste[i]
  }
  const nextIndex = Math.min(paste.length, 5)
  inputs.value[nextIndex]?.focus()
}

function verify() {
  const fullCode = code.value.join('')
  if (fullCode.length === 6) {
    router.push('/')
  } else {
    inputs.value[0]?.focus()
  }
}

onMounted(() => {
  inputs.value[0]?.focus()
})
</script>
