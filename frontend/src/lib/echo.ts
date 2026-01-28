import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { logger } from '@/lib/logger';

// @ts-expect-error - Pusher needs to be on window for Laravel Echo
window.Pusher = Pusher;

export const initEcho = (token: string) => {
  const isEnabled = import.meta.env.VITE_REVERB_ENABLED !== 'false';

  if (!isEnabled) {
    logger.info('Echo disabled (VITE_REVERB_ENABLED=false)');
    return null;
  }

  const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'reporting_key',
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${import.meta.env.VITE_API_BASE_URL || '/api'}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    },
  });

  logger.info('Echo initialized, connecting to Reverb...');

  echo.connector.pusher.connection.bind('connected', () => {
    logger.info('Connected to Reverb WebSocket');
  });

  echo.connector.pusher.connection.bind('error', (err: { type?: string; error?: { message?: string } }) => {
    logger.error('Reverb Connection Error', err);
  });

  return echo;
};
