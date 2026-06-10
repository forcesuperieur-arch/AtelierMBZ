<template>
  <div class="flex justify-end gap-2.5">
    <UButton
      v-if="showCancel"
      :label="cancelLabel"
      variant="outline"
      color="neutral"
      @click="$emit('cancel')"
    />
    <UButton
      :type="formId ? 'submit' : 'button'"
      :form="formId || undefined"
      :label="submitLabel"
      :color="submitColor"
      :variant="submitVariant"
      :loading="submitLoading"
      :disabled="submitDisabled"
      @click="formId ? undefined : $emit('submit')"
    />
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  cancelLabel?: string
  submitLabel?: string
  submitLoading?: boolean
  submitDisabled?: boolean
  submitColor?: 'primary' | 'secondary' | 'success' | 'info' | 'warning' | 'error' | 'neutral'
  submitVariant?: 'solid' | 'outline' | 'soft' | 'ghost' | 'link' | 'subtle'
  showCancel?: boolean
  formId?: string
}>(), {
  cancelLabel: 'Annuler',
  submitLabel: 'Enregistrer',
  submitColor: 'primary',
  submitVariant: 'solid',
  showCancel: true,
})

defineEmits<{
  cancel: []
  submit: []
}>()
</script>
