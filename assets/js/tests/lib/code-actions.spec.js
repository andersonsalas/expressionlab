import {
  showCopiedFeedback,
  handleCodeActionClick,
  handleCodeActionMouseDown,
  setupCodeActionListeners,
  removeCodeActionListeners,
} from '../../lib/code-actions.js';
import { handleClipboardCopy } from '../../lib/api/client.js';
import { setActivePinia, createPinia } from 'pinia';
import { useUiStore } from '../../stores/ui.js';

jest.mock('../../lib/api/client.js', () => ({
  handleClipboardCopy: jest.fn(),
}));

describe('code-actions.js', () => {
  let uiStore;

  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();
    setActivePinia(createPinia());
    uiStore = useUiStore();
    document.body.innerHTML = '';
  });

  afterEach(() => {
    removeCodeActionListeners();
    jest.useRealTimers();
  });

  describe('showCopiedFeedback', () => {
    it('temporarily switches the icon to checkmark and adds copied class', () => {
      const btn = document.createElement('button');
      btn.className = 'el-code-action-btn';
      btn.setAttribute('title', 'Copy code');
      const icon = document.createElement('span');
      icon.className = 'codicon codicon-copy';
      btn.appendChild(icon);

      showCopiedFeedback(btn);

      expect(icon.className).toBe('codicon codicon-check');
      expect(btn.classList.contains('copied')).toBe(true);
      expect(btn.getAttribute('title')).toBe('Copied!');

      jest.advanceTimersByTime(1500);

      expect(icon.className).toBe('codicon codicon-copy');
      expect(btn.classList.contains('copied')).toBe(false);
      expect(btn.getAttribute('title')).toBe('Copy code');
    });

    it('handles null safely without throwing', () => {
      expect(() => showCopiedFeedback(null)).not.toThrow();
    });
  });

  describe('handleCodeActionClick', () => {
    it('copies code when action is copy', () => {
      const btn = document.createElement('button');
      btn.className = 'el-code-action-btn el-code-copy-btn';
      btn.dataset.action = 'copy';
      btn.dataset.code = encodeURIComponent('Database.query("SELECT 1")');
      const icon = document.createElement('span');
      icon.className = 'codicon codicon-copy';
      btn.appendChild(icon);

      const event = {
        target: btn,
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
      };

      handleCodeActionClick(event);

      expect(event.preventDefault).toHaveBeenCalled();
      expect(event.stopPropagation).toHaveBeenCalled();
      expect(handleClipboardCopy).toHaveBeenCalledWith('Database.query("SELECT 1")');
      expect(btn.classList.contains('copied')).toBe(true);
    });

    it('inserts into main console when no modal is active and action is insert', () => {
      uiStore.activeModal = null;
      const spy = jest.spyOn(uiStore, 'triggerSnippetInsert');

      const btn = document.createElement('button');
      btn.className = 'el-code-action-btn el-code-insert-btn';
      btn.dataset.action = 'insert';
      btn.dataset.code = encodeURIComponent('Database.mirror("posts").fetch()');

      const event = {
        target: btn,
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
      };

      handleCodeActionClick(event);

      expect(spy).toHaveBeenCalledWith('Database.mirror("posts").fetch()');
    });

    it('inserts into library modal editor when library modal is active', () => {
      uiStore.activeModal = 'library';
      const spy = jest.spyOn(uiStore, 'triggerLibrarySnippetInsert');

      const btn = document.createElement('button');
      btn.className = 'el-code-action-btn el-code-insert-btn';
      btn.dataset.action = 'insert';
      btn.dataset.code = encodeURIComponent('Database.mirror("pages").fetch()');

      const event = {
        target: btn,
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
      };

      handleCodeActionClick(event);

      expect(spy).toHaveBeenCalledWith('Database.mirror("pages").fetch()');
    });

    it('ignores clicks outside code action buttons', () => {
      const div = document.createElement('div');
      const event = {
        target: div,
        preventDefault: jest.fn(),
        stopPropagation: jest.fn(),
      };

      handleCodeActionClick(event);
      expect(event.preventDefault).not.toHaveBeenCalled();
    });
  });

  describe('handleCodeActionMouseDown', () => {
    it('prevents default on action buttons to preserve editor focus', () => {
      const btn = document.createElement('button');
      btn.className = 'el-code-action-btn';
      const event = {
        target: btn,
        preventDefault: jest.fn(),
      };

      handleCodeActionMouseDown(event);
      expect(event.preventDefault).toHaveBeenCalled();
    });

    it('does not prevent default on other elements', () => {
      const div = document.createElement('div');
      const event = {
        target: div,
        preventDefault: jest.fn(),
      };

      handleCodeActionMouseDown(event);
      expect(event.preventDefault).not.toHaveBeenCalled();
    });
  });

  describe('setupCodeActionListeners and removeCodeActionListeners', () => {
    it('attaches and removes listeners properly', () => {
      const addSpy = jest.spyOn(document, 'addEventListener');
      const removeSpy = jest.spyOn(document, 'removeEventListener');

      setupCodeActionListeners();
      expect(addSpy).toHaveBeenCalledWith('mousedown', handleCodeActionMouseDown);
      expect(addSpy).toHaveBeenCalledWith('click', handleCodeActionClick);

      removeCodeActionListeners();
      expect(removeSpy).toHaveBeenCalledWith('mousedown', handleCodeActionMouseDown);
      expect(removeSpy).toHaveBeenCalledWith('click', handleCodeActionClick);
    });
  });
});
