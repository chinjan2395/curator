// Production static file server for the built Vite app.
// Serves the contents of the `dist` directory and falls back to
// index.html for any unmatched route so Vue Router works correctly
// in production.

const express = require('express');
const path = require('path');

const app = express();
const port = process.env.PORT || 8080;
const distDir = path.join(__dirname, 'dist');

app.use(express.static(distDir));

// SPA fallback: any route that isn't a static asset should serve index.html
// so that Vue Router can handle client-side routing.
app.get('*', (req, res) => {
  res.sendFile(path.join(distDir, 'index.html'));
});

app.listen(port, '0.0.0.0', () => {
  console.log(`Server listening on port ${port}`);
});
