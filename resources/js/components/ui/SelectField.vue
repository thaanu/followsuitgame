<script setup lang="ts">
import type { Option } from '@/types/game';
import FormField from './FormField.vue';

defineProps<{ label: string; options: Option[]; hint?: string; error?: string; id?: string }>();
const model = defineModel<string>({ required: true });
</script>

<template>
    <FormField :label="label" :hint="hint" :error="error" :for="id">
        <select :id="id" v-model="model" class="field-control appearance-none">
            <option v-for="option in options" :key="option.value" :value="option.value" class="bg-felt-900">
                {{ option.label }}
            </option>
        </select>
    </FormField>
</template>
