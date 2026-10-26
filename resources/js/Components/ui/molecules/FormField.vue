<script setup>
// Satu field form dari deskripsi { name, label, type, options?, help?, step?, placeholder? }.
// type: text | email | password | number | date | color | textarea | select | combobox | multiselect | checkbox.
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Textarea from '@/Components/ui/atoms/Textarea.vue';
import Combobox from '@/Components/ui/molecules/Combobox.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number, Boolean, Array], default: null },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const id = `field-${props.field.name}`;
const ubah = (v) => emit('update:modelValue', v);
</script>

<template>
    <div class="form-field" :class="{ 'form-field-full': field.type === 'textarea' || field.type === 'multiselect' }">
        <label v-if="field.type === 'checkbox'" class="form-check">
            <Checkbox :id="id" :model-value="Boolean(modelValue)" @update:model-value="ubah" />
            <span>{{ field.label }}</span>
        </label>
        <template v-else>
            <Label :for="id">{{ field.label }}</Label>
            <Textarea v-if="field.type === 'textarea'" :id="id" :model-value="modelValue ?? ''" rows="2" @update:model-value="ubah" />
            <Select v-else-if="field.type === 'select'" :id="id" :model-value="modelValue ?? ''" :options="field.options" placeholder="— Pilih —" @update:model-value="ubah" />
            <Combobox v-else-if="field.type === 'combobox'" :id="id" :model-value="modelValue" :options="field.options" placeholder="Cari..." @update:model-value="ubah" />
            <Combobox v-else-if="field.type === 'multiselect'" :id="id" :model-value="modelValue ?? []" :options="field.options" multiple placeholder="Pilih..." @update:model-value="ubah" />
            <Input v-else :id="id" :model-value="modelValue ?? ''" :type="field.type" :step="field.step" :placeholder="field.placeholder" @update:model-value="ubah" />
        </template>
        <p v-if="field.help" class="mt-1 mb-0 text-xs text-gray-500">{{ field.help }}</p>
        <FieldError v-if="error">{{ error }}</FieldError>
    </div>
</template>
