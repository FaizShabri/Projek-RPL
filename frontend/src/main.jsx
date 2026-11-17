import { Component, StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import App from './App.jsx';

// Error Boundary Komprehensif untuk menangkap crash di React
class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null, errorInfo: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    console.error('CRITICAL REACT CRASH:', error, errorInfo);
    this.setState({ errorInfo });
  }

  render() {
    if (this.state.hasError) {
      return (
        <div style={{ padding: '24px', fontFamily: 'sans-serif', maxWidth: '800px', margin: '40px auto', background: '#FFF5F5', border: '2px solid #E53E3E', borderRadius: '12px', color: '#9B2C2C' }}>
          <h1 style={{ fontSize: '20px', fontWeight: 'bold', marginBottom: '12px' }}>⚠️ Terjadi Error pada Komponen React:</h1>
          <p style={{ background: '#FFF', padding: '12px', borderRadius: '6px', border: '1px solid #FEB2B2', fontFamily: 'monospace', fontSize: '14px', whiteSpace: 'pre-wrap', color: '#C53030' }}>
            {this.state.error?.toString()}
          </p>
          {this.state.errorInfo?.componentStack && (
            <details style={{ marginTop: '16px' }} open>
              <summary style={{ cursor: 'pointer', fontWeight: 'bold' }}>Lokasi Komponen yang Crash (Stack Trace):</summary>
              <pre style={{ background: '#2D3748', color: '#F7FAFC', padding: '12px', borderRadius: '6px', fontSize: '12px', overflowX: 'auto', marginTop: '8px' }}>
                {this.state.errorInfo.componentStack}
              </pre>
            </details>
          )}
          <div style={{ marginTop: '20px', display: 'flex', gap: '10px' }}>
            <button
              onClick={() => {
                localStorage.clear();
                window.location.href = '/login';
              }}
              style={{ padding: '8px 16px', background: '#E53E3E', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer', fontWeight: 'bold' }}
            >
              Reset Session & Kembali ke Login
            </button>
            <button
              onClick={() => window.location.reload()}
              style={{ padding: '8px 16px', background: '#718096', color: 'white', border: 'none', borderRadius: '6px', cursor: 'pointer' }}
            >
              Reload Halaman
            </button>
          </div>
        </div>
      );
    }
    return this.props.children;
  }
}

createRoot(document.getElementById('root')).render(
  <ErrorBoundary>
    <App />
  </ErrorBoundary>,
);
