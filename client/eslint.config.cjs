const globals = require("globals")
const js = require("@eslint/js")
const pluginVue = require("eslint-plugin-vue")

module.exports = [
    js.configs.recommended,
    { ignores: [".nuxt/**", "node_modules/**", "dist/**"] },
    {
        files: ["**/*.{js,mjs,cjs,vue}"],
        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.node,
            },
            parser: require("vue-eslint-parser"),
            parserOptions: {
                ecmaVersion: "latest",
                sourceType: "module"
            }
        },
        plugins: {
            vue: pluginVue
        },
        rules: {
            "vue/require-default-prop": "off",
            "vue/no-mutating-props": "off",
            semi: ["error", "never"],
            "vue/no-v-html": "off",
            "prefer-rest-params": "off",
            "vue/valid-template-root": "off",
            "no-undef": "off",
            "no-unused-vars": ["error", {
                "argsIgnorePattern": "^_",
            }],
        },
    }
]
