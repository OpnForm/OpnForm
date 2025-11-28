export default defineAppConfig({
    icon: {
        cssLayer: 'base',
    },
    ui: {
        colors: {
            primary: 'blue',
            secondary: 'blue',
            success: 'green',
            error: 'red',
            warning: 'amber',
            info: 'blue',
            neutral: 'neutral',
            form: 'form'
        },
        
        tabs: {
            slots: {
                root: 'space-y-0',
                list: 'h-auto',
                trigger: 'h-[30px]'
            }
        },

        keyboard: {
            defaultVariant: 'subtle',
        },

        icons: {
            arrowLeft: 'i-heroicons-arrow-left',
            arrowRight: 'i-heroicons-arrow-right', 
            check: 'i-heroicons-check',
            chevronDoubleLeft: 'i-heroicons-chevron-double-left',
            chevronDoubleRight: 'i-heroicons-chevron-double-right',
            chevronDown: 'i-heroicons-chevron-down',
            chevronLeft: 'i-heroicons-chevron-left',
            chevronRight: 'i-heroicons-chevron-right',
            chevronUp: 'i-heroicons-chevron-up',
            close: 'i-heroicons-x-mark',
            ellipsis: 'i-heroicons-ellipsis-horizontal',
            external: 'i-heroicons-arrow-up-right',
            folder: 'i-heroicons-folder',
            folderOpen: 'i-heroicons-folder-open',
            loading: 'i-heroicons-arrow-path',
            minus: 'i-heroicons-minus',
            plus: 'i-heroicons-plus',
            search: 'i-heroicons-magnifying-glass'
        },

        table: {
            slots: {
                td: 'p-4 text-sm text-muted whitespace-normal [&:has([role=checkbox])]:pe-0'
            },
        }
    }
})
