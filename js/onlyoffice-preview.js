(function () {
  for (onlyofficeData of drupalSettings.onlyofficeData) {
    if (typeof DocsAPI !== 'undefined') {
      new DocsAPI.DocEditor(onlyofficeData.editor_id, onlyofficeData.config);
    }
  }
})();
