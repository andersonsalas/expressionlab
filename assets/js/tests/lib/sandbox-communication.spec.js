import { sendToSandbox, createSandboxMessageHandler, requestFromSandbox } from '../../lib/sandbox-communication.js';

describe('sandbox-communication.js', () => {
  let mockIframe;
  let mockPostMessage;

  beforeEach(() => {
    mockPostMessage = jest.fn();
    mockIframe = {
      contentWindow: {
        postMessage: mockPostMessage,
      },
    };
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
    jest.clearAllMocks();
  });

  describe('sendToSandbox', () => {
    it('sends structured message with action and payload to iframe.contentWindow', () => {
      sendToSandbox(mockIframe, 'eval', { code: '1 + 1' });
      expect(mockPostMessage).toHaveBeenCalledTimes(1);
      expect(mockPostMessage).toHaveBeenCalledWith(
        {
          action: 'eval',
          payload: { code: '1 + 1' },
          code: '1 + 1',
        },
        '*'
      );
    });

    it('handles non-object payload gracefully', () => {
      sendToSandbox(mockIframe, 'ping', 'simple-payload');
      expect(mockPostMessage).toHaveBeenCalledWith(
        {
          action: 'ping',
          payload: 'simple-payload',
        },
        '*'
      );
    });

    it('does not throw and returns early when iframe or contentWindow is missing', () => {
      expect(() => sendToSandbox(null, 'test')).not.toThrow();
      expect(() => sendToSandbox({}, 'test')).not.toThrow();
      expect(mockPostMessage).not.toHaveBeenCalled();
    });
  });

  describe('createSandboxMessageHandler', () => {
    it('dispatches action to registered handler when origin matches', () => {
      const evalHandler = jest.fn();
      const listener = createSandboxMessageHandler({
        eval_result: evalHandler,
      });

      const event = {
        origin: window.location.origin,
        data: {
          action: 'eval_result',
          payload: { result: 42 },
        },
      };

      listener(event);
      expect(evalHandler).toHaveBeenCalledTimes(1);
      expect(evalHandler).toHaveBeenCalledWith({ result: 42 }, event);
    });

    it('ignores messages from invalid origins', () => {
      const handler = jest.fn();
      const listener = createSandboxMessageHandler({ ping: handler });

      const maliciousEvent = {
        origin: 'https://evil-hacker.com',
        data: { action: 'ping', payload: 'attack' },
      };

      listener(maliciousEvent);
      expect(handler).not.toHaveBeenCalled();
    });

    it('ignores messages without an action name', () => {
      const handler = jest.fn();
      const listener = createSandboxMessageHandler({ ping: handler });

      listener({
        origin: window.location.origin,
        data: { payload: 'no action' },
      });
      expect(handler).not.toHaveBeenCalled();
    });

    it('ignores unregistered actions without error', () => {
      const handler = jest.fn();
      const listener = createSandboxMessageHandler({ registered: handler });

      expect(() => {
        listener({
          origin: window.location.origin,
          data: { action: 'unregistered', payload: 123 },
        });
      }).not.toThrow();
      expect(handler).not.toHaveBeenCalled();
    });
  });

  describe('requestFromSandbox', () => {
    it('resolves with payload when response message arrives before timeout', async () => {
      const promise = requestFromSandbox(mockIframe, 'get_height', null, 2000);

      // Verify command sent
      expect(mockPostMessage).toHaveBeenCalledWith(
        expect.objectContaining({ action: 'get_height' }),
        '*'
      );

      // Simulate response event
      const responseEvent = new MessageEvent('message', {
        origin: window.location.origin,
        data: {
          action: 'get_height_response',
          payload: { height: 450 },
        },
      });

      window.dispatchEvent(responseEvent);

      const result = await promise;
      expect(result).toEqual({ height: 450 });
    });

    it('rejects with timeout error when no response is received', async () => {
      const promise = requestFromSandbox(mockIframe, 'slow_action', null, 3000);

      // Advance timers past timeout
      jest.advanceTimersByTime(3100);

      await expect(promise).rejects.toThrow('Sandbox request "slow_action" timed out after 3000ms');
    });

    it('cleans up event listener after resolving', async () => {
      const removeSpy = jest.spyOn(window, 'removeEventListener');
      const promise = requestFromSandbox(mockIframe, 'quick_action', null, 2000);

      window.dispatchEvent(
        new MessageEvent('message', {
          origin: window.location.origin,
          data: { action: 'quick_action_response', payload: 'ok' },
        })
      );

      await promise;
      expect(removeSpy).toHaveBeenCalledWith('message', expect.any(Function));
      removeSpy.mockRestore();
    });
  });
});
