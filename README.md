# Meta Fields Manager

A WordPress plugin that adds meta title, description, and image fields to posts and pages with Open Graph & Twitter Cards support.

## Features

- Add custom meta title, description, and image for posts and pages
- Open Graph meta tags support for better social media sharing
- Twitter Cards support for better Twitter sharing
- Secure image validation and rate limiting
- User-friendly interface in the block editor
- Responsive design

## Installation

1. Download the plugin
2. Upload the plugin files to the `/wp-content/plugins/meta-fields-manager` directory
3. Activate the plugin through the 'Plugins' screen in WordPress
4. Use the Meta panel in the post editor to configure meta fields

## Development

### Prerequisites

- Node.js (v14 or higher)
- npm (v6 or higher)

### Setup

1. Clone the repository
2. Install dependencies:
   ```bash
   npm install
   ```
3. Build the plugin:
   ```bash
   npm run build
   ```

### Development Commands

- `npm run build` - Build the plugin for production
- `npm run start` - Start development mode with hot reloading
- `npm run check-engines` - Check Node.js version
- `npm run check-licenses` - Check package licenses
- `npm run format` - Format code
- `npm run lint:css` - Lint CSS files
- `npm run lint:js` - Lint JavaScript files
- `npm run lint:md:docs` - Lint Markdown files
- `npm run lint:pkg-json` - Lint package.json
- `npm run test:e2e` - Run end-to-end tests
- `npm run test:unit` - Run unit tests
- `npm run plugin-zip` - Create a zip file of the plugin

## Security

The plugin includes several security features:

- Input sanitization for all meta fields
- Rate limiting for meta updates
- Image URL validation
- Nonce verification for REST API endpoints
- Capability checks for all operations
- Domain whitelisting for images
- Proper error handling and user feedback

## License

This plugin is licensed under the GPL v2 or later.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. 