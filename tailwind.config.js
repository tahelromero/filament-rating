// Conditional config based on whether we're in a standalone build or integrated environment
let config = {
    content: [
        './resources/**/*.{php,html,js,css}',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
}

// Try to load Filament preset if available (when integrated with a Filament app)
try {
    const preset = require('./vendor/filament/filament/tailwind.config.preset')
    config = {
        presets: [preset],
        content: [
            './app/Filament/**/*.php',
            './resources/views/filament/**/*.blade.php',
            './vendor/filament/**/*.blade.php',
        ],
    }
} catch (e) {
    // Fallback to standalone config when vendor directory doesn't exist
    console.log('Using standalone Tailwind config (vendor directory not found)')
}

module.exports = config
