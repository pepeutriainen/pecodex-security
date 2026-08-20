import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import SecurityApp from './SecurityApp.jsx'
import VisibilityApp from './VisibilityApp.jsx'

const isVisibilityApp = typeof window.pgmVisibilityApi !== 'undefined';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    {isVisibilityApp ? <VisibilityApp /> : <SecurityApp />}
  </StrictMode>,
)
