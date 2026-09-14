/**
 * Outline data processor for Expression Lab.
 *
 * Transforms structured outline data from the server into the flat/tree
 * structures used by the UI components (sidebar, autocomplete).
 *
 * Server format (structured JSON):
 *   objects:      { Name: { methods: {}, constants: {}, properties: {} } }
 *   functions:    { name: { doc: {...}, insertText: "..." } }
 *   constants:    { name: { doc: {...}, insertText: "..." } }
 *   typeRegistry: { TypeName: { member: { doc: {...}, insertText, type, returnType } } }
 */

/**
 * Process outline data into flat and tree structures for the UI.
 *
 * @param {object} outlineData - The structured outline data from the server.
 * @returns {{ outlineFlat: Array, outlineTree: Array, outlineChained: object|null }}
 */
export function processOutlineData(outlineData) {
  const outlineFlat = [];
  const outlineTree = [];

  // Collect root object names for chain resolution.
  const rootObjects = new Set(Object.keys(outlineData.objects || {}));

  // Objects
  for (const [objName, objData] of Object.entries(outlineData.objects || {})) {
    const objNode = {
      label: objName,
      kind: 'Object',
      icon: 'codicon-symbol-class',
      expanded: false,
      methods: [],
    };

    for (const [methodName, methodDoc] of Object.entries(objData.methods || {})) {
      const methodItem = {
        label: `${objName}.${methodName}`,
        kind: 'Method',
        insertText:
          methodDoc.insertText && methodDoc.insertText !== ''
            ? methodDoc.insertText
            : `${objName}.${methodName}()`,
        doc: methodDoc.doc,
      };
      outlineFlat.push(methodItem);
      objNode.methods.push({
        label: methodName,
        kind: 'Method',
        icon: 'codicon-symbol-method',
        insertText: methodItem.insertText,
        doc: methodDoc.doc,
      });
    }

    if (objData.constants) {
      for (const [constName, constDoc] of Object.entries(objData.constants)) {
        const constItem = {
          label: `${objName}.${constName}`,
          kind: 'Constant',
          insertText:
            constDoc.insertText && constDoc.insertText !== ''
              ? constDoc.insertText
              : `${objName}.${constName}`,
          doc: constDoc.doc,
        };
        outlineFlat.push(constItem);
        objNode.methods.push({
          label: constName,
          kind: 'Constant',
          icon: 'codicon-symbol-constant',
          insertText: constItem.insertText,
          doc: constDoc.doc,
        });
      }
    }

    if (objData.properties) {
      for (const [propName, propDoc] of Object.entries(objData.properties)) {
        const propItem = {
          label: `${objName}.${propName}`,
          kind: 'Property',
          insertText:
            propDoc.insertText && propDoc.insertText !== ''
              ? propDoc.insertText
              : `${objName}.${propName}`,
          doc: propDoc.doc,
        };
        outlineFlat.push(propItem);
        objNode.methods.push({
          label: propName,
          kind: 'Property',
          icon: 'codicon-symbol-property',
          insertText: propItem.insertText,
          doc: propDoc.doc,
        });
      }
    }

    outlineTree.push(objNode);
  }

  // Functions
  if (outlineData.functions && Object.keys(outlineData.functions).length > 0) {
    const funcNode = {
      label: 'Functions',
      kind: 'Category',
      icon: 'codicon-library',
      expanded: true,
      methods: [],
    };

    for (const [funcName, funcDoc] of Object.entries(outlineData.functions)) {
      const funcItem = {
        label: funcName,
        kind: 'Function',
        insertText:
          funcDoc.insertText && funcDoc.insertText !== ''
            ? funcDoc.insertText
            : `${funcName}()`,
        doc: funcDoc.doc,
      };

      outlineFlat.push(funcItem);

      funcNode.methods.push({
        label: funcName,
        kind: 'Function',
        icon: 'codicon-symbol-function',
        insertText: funcItem.insertText,
        doc: funcDoc.doc,
      });
    }
    outlineTree.unshift(funcNode);
  }

  // Constants
  if (outlineData.constants && Object.keys(outlineData.constants).length > 0) {
    const constNode = {
      label: 'Constants',
      kind: 'Category',
      icon: 'codicon-symbol-constant',
      expanded: true,
      methods: [],
    };

    for (const [constName, constDoc] of Object.entries(outlineData.constants)) {
      const constItem = {
        label: constName,
        kind: 'Constant',
        insertText:
          constDoc.insertText && constDoc.insertText !== ''
            ? constDoc.insertText
            : constName,
        doc: constDoc.doc,
      };

      outlineFlat.push(constItem);

      constNode.methods.push({
        label: constName,
        kind: 'Constant',
        icon: 'codicon-symbol-constant',
        insertText: constItem.insertText,
        doc: constDoc.doc,
      });
    }
    outlineTree.push(constNode);
  }

  return {
    outlineFlat,
    outlineTree,
    outlineChained: { typeRegistry: outlineData.typeRegistry, rootObjects },
  };
}
