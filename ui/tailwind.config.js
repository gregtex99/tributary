/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        health: '#4A9B7F',
        calendar: '#5B8FB9',
        tasks: '#6B7C8F',
        relationships: '#D4956A',
        finance: '#3D5A6C',
      }
    },
  },
  plugins: [],
}
