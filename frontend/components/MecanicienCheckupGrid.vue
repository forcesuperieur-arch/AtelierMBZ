<template>
  <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
    <button
      v-for="item in items"
      :key="item.key"
      type="button"
      class="checkup-tile"
      :class="{
        'checkup-tile--ok': checkup[item.key] === 'ok',
        'checkup-tile--nok': checkup[item.key] === 'nok',
      }"
      @click="$emit('toggle', item.key)"
    >
      <div class="checkup-tile-icon">
        <UIcon
          v-if="checkup[item.key] === 'ok'"
          name="i-heroicons-check-circle"
          class="w-6 h-6"
          style="color:#10B981"
        />
        <UIcon
          v-else-if="checkup[item.key] === 'nok'"
          name="i-heroicons-x-circle"
          class="w-6 h-6"
          style="color:#EF4444"
        />
        <UIcon
          v-else
          name="i-heroicons-question-mark-circle"
          class="w-6 h-6 text-gray-500"
        />
      </div>
      <div class="checkup-tile-label">{{ item.label }}</div>
      <div
        class="checkup-tile-status"
        :class="{
          'text-green-400': checkup[item.key] === 'ok',
          'text-red-400': checkup[item.key] === 'nok',
          'text-gray-500': !checkup[item.key],
        }"
      >
        {{ checkup[item.key] === 'ok' ? 'OK' : checkup[item.key] === 'nok' ? 'KO' : 'Non vérifié' }}
      </div>
      <img
        v-if="photos && photos[item.key]"
        :src="photos[item.key]"
        class="checkup-tile-photo"
        alt=""
      />
    </button>
  </div>
</template>

<script setup lang="ts">
interface CheckupItem {
  key: string
  label: string
}

defineProps<{
  checkup: Record<string, string>
  items: CheckupItem[]
  photos?: Record<string, string>
}>()

defineEmits<{
  toggle: [key: string]
}>()
</script>

<style scoped>
.checkup-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 80px;
  padding: 10px 8px;
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.06);
  background: rgba(255,255,255,0.02);
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
  touch-action: manipulation;
}
.checkup-tile:active {
  background: rgba(255,255,255,0.05);
}
.checkup-tile--ok {
  background: rgba(16,185,129,0.06);
  border-color: rgba(16,185,129,0.2);
}
.checkup-tile--nok {
  background: rgba(239,68,68,0.06);
  border-color: rgba(239,68,68,0.2);
}
.checkup-tile-label {
  font-size: 12px;
  font-weight: 600;
  color: #E8E9ED;
  text-align: center;
  line-height: 1.2;
}
.checkup-tile-status {
  font-size: 11px;
  font-weight: 700;
}
.checkup-tile-photo {
  width: 32px;
  height: 32px;
  object-fit: cover;
  border-radius: 6px;
  margin-top: 2px;
}
</style>
