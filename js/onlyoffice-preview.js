(function () {
  if (typeof DocsAPI !== 'undefined') {

    let editors = document.getElementsByClassName("onlyoffice-editor");

    let count = editors.length;
    for (let i = 0; i < count; i++) {
      let dataId = editors[0].id;
      editors[0].id =editors[0].id + "_" + i;
      new DocsAPI.DocEditor(editors[0].id, drupalSettings.onlyofficeData[dataId].config);
    }
  }
})();
