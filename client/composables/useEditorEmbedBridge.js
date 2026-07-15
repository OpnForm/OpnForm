import {
  emitEditorFormDeleted,
  emitEditorFormSaved,
  emitEditorNavigateBack,
} from '~/lib/sdk/editorEmbedBridge'

export function useEditorEmbedBridge() {
  return {
    emitFormSaved: emitEditorFormSaved,
    emitFormDeleted: emitEditorFormDeleted,
    emitNavigateBack: emitEditorNavigateBack,
  }
}
