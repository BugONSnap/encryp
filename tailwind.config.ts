import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import type { Config } from 'tailwindcss';

export default {
	content: ['./src/**/*.{html,js,svelte,ts}'],

	theme: {
		extend: {
			fontFamily: {
				dynapuff: ['"DynaPuff"', 'sans-serif'],
				caveat: ['"Caveat Brush"', 'sans-serif'],
				karla: ['"Karla"', 'sans-serif'],
				macondo: ['"Macondo"','sans-serif '],
				concert: ['"Concert One"', 'sans-serif'],
			  }
		}
	},

	plugins: [typography, forms]
} satisfies Config;
