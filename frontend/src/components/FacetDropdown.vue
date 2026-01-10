<template>
    <div class="relative" ref="root">
        <!-- Кнопка -->
        <button
            type="button"
            class="w-full text-xs border rounded px-2 py-1 text-left bg-white flex justify-between items-center"
            @click="toggle"
            @keydown.down.prevent="openAndFocus(0)"
            @keydown.enter.prevent="toggle"
        >
            <span v-if="selectedLabels.length">
                {{ selectedLabels.join(', ') }}
            </span>
            <span v-else class="text-gray-400">
                {{ placeholder }}
            </span>

            <svg class="w-3 h-3 ml-2 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                      d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                      clip-rule="evenodd"/>
            </svg>
        </button>

        <!-- Dropdown -->
        <div
            v-if="open"
            class="absolute z-50 mt-1 w-full bg-white border rounded shadow"
            @keydown.esc.prevent="close"
            @keydown.down.prevent="focusNext"
            @keydown.up.prevent="focusPrev"
            @keydown.enter.prevent="toggleFocused"
        >
            <!-- Поиск -->
            <input
                ref="searchInput"
                v-model="search"
                type="text"
                placeholder="Поиск..."
                class="w-full text-xs px-2 py-1 border-b outline-none"
            />

            <!-- Список -->
            <ul class="max-h-48 overflow-auto">
                <li
                    v-for="(option, index) in visibleOptions"
                    :key="option.key"
                    ref="items"
                    class="flex items-center gap-2 px-2 py-1 text-xs cursor-pointer"
                    :class="{
                        'bg-gray-100': index === focusedIndex
                    }"
                    @click="toggleOption(option.value)"
                    @mouseenter="focusedIndex = index"
                >
                    <span class="w-4 text-center">
                        <svg
                            v-if="isSelected(option.value)"
                            class="w-3 h-3 text-green-600"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path fill-rule="evenodd"
                                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 11.586l7.293-7.293a1 1 0 011.414 0z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </span>

                    <span class="truncate">
                        {{ option.label }}
                    </span>
                </li>

                <li v-if="!visibleOptions.length" class="px-2 py-2 text-xs text-gray-400">
                    Ничего не найдено
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
export default {
    name: 'FacetDropdown',

    props: {
        modelValue: {
            type: Array,
            required: true,
        },
        options: {
            type: Array,
            required: true,
        },
        dictionary: {
            type: Object,
            default: () => null,
        },
        labelMap: {
            type: Object,
            default: () => null,
        },
        placeholder: {
            type: String,
            default: 'Выбрать',
        },
        allowNull: {
            type: Boolean,
            default: true,
        },
        nullLabel: {
            type: String,
            default: 'Без значения',
        },
    },

    emits: ['update:modelValue', 'change'],

    data() {
        return {
            open: false,
            search: '',
            focusedIndex: -1,
        };
    },

    computed: {
        normalizedOptions() {
            return this.options.map(v => {
                const value = v === null && this.allowNull ? '' : v;

                let label;

                if (v === null) {
                    label = this.nullLabel;
                } else if (this.labelMap?.[v] !== undefined) {
                    label = this.labelMap[v];
                } else if (this.dictionary?.[v]?.name) {
                    label = this.dictionary[v].name;
                } else {
                    label = String(v);
                }

                return {
                    key: v ?? '__null__',
                    value,
                    label,
                };
            });
        },

        visibleOptions() {
            const s = this.search.toLowerCase();

            return this.normalizedOptions.filter(o =>
                o.label.toLowerCase().includes(s)
            );
        },

        selectedLabels() {
            return this.modelValue.map(v => {
                if (v === '') return this.nullLabel;
                if (this.labelMap?.[v] !== undefined) return this.labelMap[v];
                if (this.dictionary?.[v]?.name) return this.dictionary[v].name;
                return String(v);
            });
        },
    },

    mounted() {
        document.addEventListener('click', this.onOutsideClick);
    },

    beforeUnmount() {
        document.removeEventListener('click', this.onOutsideClick);
    },

    methods: {
        toggle() {
            this.open ? this.close() : this.openDropdown();
        },

        openDropdown() {
            this.open = true;
            this.$nextTick(() => {
                this.$refs.searchInput?.focus();
            });
        },

        close() {
            this.open = false;
            this.search = '';
            this.focusedIndex = -1;
        },

        onOutsideClick(e) {
            if (!this.$refs.root.contains(e.target)) {
                this.close();
            }
        },

        isSelected(value) {
            return this.modelValue.includes(value);
        },

        toggleOption(value) {
            const next = [...this.modelValue];
            const i = next.indexOf(value);

            i === -1 ? next.push(value) : next.splice(i, 1);

            this.$emit('update:modelValue', next);
            this.$emit('change');
        },

        openAndFocus(index) {
            this.openDropdown();
            this.focusedIndex = index;
        },

        focusNext() {
            if (!this.visibleOptions.length) return;
            this.focusedIndex = (this.focusedIndex + 1) % this.visibleOptions.length;
            this.scrollIntoView();
        },

        focusPrev() {
            if (!this.visibleOptions.length) return;
            this.focusedIndex =
                (this.focusedIndex - 1 + this.visibleOptions.length) % this.visibleOptions.length;
            this.scrollIntoView();
        },

        toggleFocused() {
            if (this.focusedIndex >= 0) {
                this.toggleOption(this.visibleOptions[this.focusedIndex].value);
            }
        },

        scrollIntoView() {
            this.$nextTick(() => {
                const el = this.$refs.items?.[this.focusedIndex];
                el?.scrollIntoView({ block: 'nearest' });
            });
        },
    },
};
</script>
