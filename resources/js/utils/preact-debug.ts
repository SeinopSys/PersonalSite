/* eslint-disable import/first */
// Must be the first import
// @see https://preactjs.com/guide/v10/debugging/#strip-devtools-from-production
if (process.env.NODE_ENV === 'development') {
  // Must use require here as import statements are only allowed
  // to exist at top-level.
  // eslint-disable-next-line global-require
  require('preact/debug');
}
