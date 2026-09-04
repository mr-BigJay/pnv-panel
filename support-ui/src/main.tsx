import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { AdminApp } from './AdminApp';
import { UserApp } from './UserApp';
import { getSupportConfig } from './types';
import './index.css';

const rootEl = document.getElementById('support-v2-root');

if (rootEl) {
  const config = getSupportConfig();
  const App = config.role === 'user' ? UserApp : AdminApp;

  createRoot(rootEl).render(
    <StrictMode>
      <App />
    </StrictMode>,
  );
}
