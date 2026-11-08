import { defineConfig } from 'eslint/config';
import noJQuery from 'eslint-plugin-no-jquery';
import prettierConfig from 'eslint-plugin-prettier/recommended';
import ymlConfig from 'eslint-plugin-yml';
import jsdoc from 'eslint-plugin-jsdoc';
import js from '@eslint/js';
import globals from 'globals';

export default defineConfig(
  {
    ignores: [
      '.github/**/*',
      'assets/**/*',
      'node_modules/**/*',
      'vendor/**/*',
    ],
  },
  js.configs.recommended,
  jsdoc.configs['flat/recommended'],
  prettierConfig,
  ymlConfig.configs['flat/recommended'],
  {
    plugins: {
      'no-jquery': noJQuery,
    },
    languageOptions: {
      ecmaVersion: 2020,
      globals: {
        ...globals.browser,
        ...globals.node,

        Drupal: 'readonly',
        drupalSettings: 'readonly',
        drupalTranslations: 'readonly',
        jQuery: 'readonly',
        _: 'readonly',
        Cookies: 'readonly',
        htmx: 'readonly',
        loadjs: 'readonly',
        Shepherd: 'readonly',
        Sortable: 'readonly',
        once: 'readonly',
        CKEditor5: 'readonly',
        CKEDITOR: 'readonly',
        tabbable: 'readonly',
        transliteration: 'readonly',
        bodyScrollLock: 'readonly',
        FloatingUIDOM: 'readonly',

        DocsAPI: 'readonly',
      },
    },
    rules: {
      'prettier/prettier': 'error',
      'no-unexpected-multiline': ['off'],
      'consistent-return': ['off'],
      'no-underscore-dangle': ['off'],
      'max-nested-callbacks': ['warn', 3],
      'no-plusplus': [
        'warn',
        {
          allowForLoopAfterthoughts: true,
        },
      ],
      'no-param-reassign': ['off'],
      'no-prototype-builtins': ['off'],
      'no-unused-vars': ['warn'],
      'operator-linebreak': [
        'error',
        'after',
        {
          overrides: {
            '?': 'ignore',
            ':': 'ignore',
          },
        },
      ],
      'yml/indent': ['error', 2],
      'jsdoc/tag-lines': ['warn', 'any', { startLines: 1 }],
      ...noJQuery.configs.all.rules,
    },
    settings: {
      jsdoc: {
        tagNamePreference: {
          returns: 'return',
          property: 'prop',
        },
      },
    },
  },
);
