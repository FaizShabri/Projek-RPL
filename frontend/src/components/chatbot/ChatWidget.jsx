import { useState, useRef, useEffect } from 'react';
import ReactMarkdown from 'react-markdown';
import { useAuth } from '../../contexts/AuthContext';
import api from '../../api/client';

export default function ChatWidget() {
  const { user, activeRole } = useAuth();
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [isDemoMode, setIsDemoMode] = useState(false);
  const messagesEndRef = useRef(null);
  const abortControllerRef = useRef(null);

  // Suggested quick prompts per role
  const quickPrompts = {
    customer: [
      'Cek status pesanan aktif saya',
      'Bagaimana cara membatalkan pesanan?',
      'Cari rekomendasi toko laundry terpercaya',
      'Saya ingin lapor cucian rusak / dispute',
      'Cek notifikasi terbaru saya',
    ],
    owner_toko: [
      'Ringkasan performa & omzet toko minggu ini',
      'Bandingkan tarif toko saya dengan pasar',
      'Cek ulasan customer terbaru',
    ],
    admin_toko: [
      'Ringkasan performa toko',
      'Cek ulasan customer terbaru',
    ],
    platform_owner: [
      'Ringkasan analitik platform & performa toko',
      'Deteksi anomali toko mencurigakan',
    ],
    admin_owner: [
      'Ringkasan analitik platform & pertumbuhan',
      'Deteksi anomali operasional toko',
    ],
    kurir: [
      'Panduan tugas penjemputan & pengantaran',
      'Bagaimana sistem penagihan tunai COD?',
    ],
  };

  const currentPrompts = quickPrompts[activeRole] || quickPrompts.customer;

  // Auto scroll to bottom
  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    if (isOpen) {
      scrollToBottom();
    }
  }, [messages, isOpen, loading]);

  // Initial welcome message when widget is opened for the first time
  useEffect(() => {
    if (isOpen && messages.length === 0) {
      const welcomeMap = {
        customer: `Halo **${user?.name || 'Pelanggan'}**! Saya asisten AI Pusat Laundry.\nAda yang bisa saya bantu terkait cek status pesanan, tracking kurir, notifikasi, atau rekomendasi toko?`,
        owner_toko: `Halo Mitra **${user?.name || 'Owner'}**! Saya asisten AI toko Anda.\nSiap membantu analisa omzet, perbandingan tarif pasar, dan ringkasan ulasan pelanggan.`,
        admin_toko: `Halo **${user?.name || 'Admin Toko'}**! Saya siap membantu pengecekan operasional cucian dan performa toko Anda.`,
        platform_owner: `Selamat datang, **Platform Owner**.\nSaya siap menyajikan analitik platform, ringkasan aktivitas, dan deteksi anomali operasional.`,
        admin_owner: `Halo **Admin Owner**.\nSaya siap membantu pemantauan analitik platform, tinjauan dokumen toko, dan deteksi anomali.`,
        kurir: `Halo Mitra Kurir **${user?.name || 'Kurir'}**!\nAda yang perlu ditanyakan seputar tugas penjemputan atau prosedur pembayaran tunai COD?`,
      };

      setMessages([
        {
          role: 'assistant',
          content: welcomeMap[activeRole] || `Halo! Ada yang bisa saya bantu seputar layanan Pusat Laundry hari ini?`,
        },
      ]);
    }
  }, [isOpen, activeRole, user]);

  const handleSend = async (messageToSend, isRetry = false) => {
    const text = (messageToSend || input).trim();
    if (!text || loading) return;

    // Jika retry, buang pesan error terakhir agar tidak bertumpuk
    let baseMessages = messages;
    if (isRetry && baseMessages.length > 0 && baseMessages[baseMessages.length - 1].isError) {
      baseMessages = baseMessages.slice(0, -1);
    }

    const newMessages = isRetry
      ? baseMessages
      : [...baseMessages, { role: 'user', content: text }];

    setMessages(newMessages);
    if (!isRetry) setInput('');
    setLoading(true);

    // Batalkan request sebelumnya jika masih menggantung
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    const controller = new AbortController();
    abortControllerRef.current = controller;

    // Timeout client-side 40 detik
    const timeoutId = setTimeout(() => {
      controller.abort();
    }, 40000);

    try {
      // Ambil riwayat percakapan bersih (hanya user dan assistant yang berhasil)
      const history = newMessages
        .filter((m) => !m.isError)
        .slice(-6)
        .map((m) => ({
          role: m.role,
          content: m.content,
        }));

      const res = await api.post(
        '/chatbot/message',
        {
          message: text,
          history,
        },
        { signal: controller.signal }
      );

      clearTimeout(timeoutId);

      if (res.is_demo !== undefined) {
        setIsDemoMode(Boolean(res.is_demo));
      }

      setMessages([
        ...newMessages,
        {
          role: 'assistant',
          content: res.reply || 'Permintaan Anda telah selesai diproses.',
          isError: false,
        },
      ]);
    } catch (err) {
      clearTimeout(timeoutId);

      let errorMsg = 'Maaf, terjadi kendala koneksi dengan asisten AI. Silakan coba lagi.';
      if (err.name === 'AbortError') {
        errorMsg = 'Waktu tunggu respon asisten AI habis (timeout). Silakan klik Coba Lagi.';
      } else if (err.message) {
        errorMsg = err.message;
      }

      setMessages([
        ...newMessages,
        {
          role: 'assistant',
          content: errorMsg,
          isError: true,
          failedText: text,
        },
      ]);
    } finally {
      setLoading(false);
      abortControllerRef.current = null;
    }
  };

  // If user is not logged in, don't display widget
  if (!user) return null;

  return (
    <div className="fixed bottom-6 right-6 z-50 font-sans">
      {/* Floating Toggle Button */}
      {!isOpen && (
        <button
          onClick={() => setIsOpen(true)}
          className="flex items-center gap-2.5 px-4 py-3 bg-[#1A3A5C] text-white rounded-full shadow-2xl hover:bg-[#142e4a] hover:scale-105 active:scale-95 transition-all duration-200 border-2 border-white/20 cursor-pointer"
          title="Tanya AI Asisten"
        >
          <div className="relative">
            <span className="text-xl">🤖</span>
            <span className="absolute -top-1 -right-1 w-2.5 h-2.5 bg-[#2ECC71] rounded-full border-2 border-[#1A3A5C]"></span>
          </div>
          <span className="text-sm font-semibold pr-1">Tanya Asisten AI</span>
        </button>
      )}

      {/* Chat Window */}
      {isOpen && (
        <div className="flex flex-col w-[380px] sm:w-[430px] h-[570px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden animate-in fade-in slide-in-from-bottom-4 duration-200">
          {/* Header */}
          <div className="flex items-center justify-between px-4 py-3 bg-[#1A3A5C] text-white">
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center text-lg border border-white/20">
                🤖
              </div>
              <div>
                <h3 className="text-sm font-bold leading-tight">Asisten AI Pusat Laundry</h3>
                <div className="flex items-center gap-1.5 mt-0.5">
                  <span className="w-2 h-2 rounded-full bg-[#2ECC71]"></span>
                  <p className="text-[11px] text-gray-200 capitalize">
                    Peran: {activeRole?.replace('_', ' ')}
                  </p>
                </div>
              </div>
            </div>
            <button
              onClick={() => setIsOpen(false)}
              className="p-1 rounded-lg text-gray-300 hover:text-white hover:bg-white/10 transition-colors cursor-pointer"
              title="Tutup Chat"
            >
              ✕
            </button>
          </div>

          {/* Subheader / Mode & Security Badge */}
          <div className="px-3.5 py-1.5 bg-[#F5F7FA] border-b border-gray-200 flex items-center justify-between text-[11px] text-gray-600">
            <span className="flex items-center gap-1 font-medium">
              🛡️ <span>Akses Read-Only Terproteksi</span>
            </span>
            {isDemoMode ? (
              <span className="text-[10px] bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full font-semibold border border-amber-200" title="Simulasi cerdas lokal tanpa real API key">
                Mode Demo (Simulasi Cerdas Lokal)
              </span>
            ) : (
              <span className="text-[10px] bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full font-semibold border border-blue-200">
                Claude 3.5 Sonnet
              </span>
            )}
          </div>

          {/* Messages Container */}
          <div className="flex-1 overflow-y-auto p-4 space-y-3.5 bg-gray-50/50">
            {messages.map((m, idx) => (
              <div
                key={idx}
                className={`flex flex-col ${m.role === 'user' ? 'items-end' : 'items-start'}`}
              >
                <div
                  className={`max-w-[88%] rounded-2xl px-3.5 py-2.5 text-xs sm:text-sm leading-relaxed ${
                    m.role === 'user'
                      ? 'bg-[#4A90D9] text-white rounded-br-xs shadow-sm'
                      : m.isError
                      ? 'bg-red-50 text-red-800 border border-red-200 rounded-bl-xs'
                      : 'bg-white text-gray-800 border border-gray-200 rounded-bl-xs shadow-xs'
                  }`}
                >
                  {m.role === 'user' ? (
                    <p className="whitespace-pre-wrap">{m.content}</p>
                  ) : (
                    <div className="prose prose-sm max-w-none text-xs sm:text-sm text-gray-800 prose-p:my-1 prose-ul:my-1.5 prose-li:my-0.5 prose-strong:text-gray-900 prose-code:text-[#1A3A5C] prose-code:bg-gray-100 prose-code:px-1 prose-code:rounded">
                      <ReactMarkdown>{m.content}</ReactMarkdown>
                    </div>
                  )}

                  {/* Tombol Coba Lagi jika Error */}
                  {m.isError && m.failedText && (
                    <button
                      onClick={() => handleSend(m.failedText, true)}
                      className="mt-2 text-[11px] font-bold text-red-700 bg-red-100 hover:bg-red-200 px-2.5 py-1 rounded-md transition-colors flex items-center gap-1 cursor-pointer"
                    >
                      🔄 Coba Lagi
                    </button>
                  )}
                </div>
              </div>
            ))}

            {loading && (
              <div className="flex items-center gap-2.5 p-3 bg-white border border-gray-200 rounded-2xl w-fit text-xs text-gray-600 shadow-xs animate-pulse">
                <span className="w-2 h-2 rounded-full bg-primary animate-ping"></span>
                <span>Asisten sedang mencari data & merangkum jawaban...</span>
              </div>
            )}

            <div ref={messagesEndRef} />
          </div>

          {/* Suggested Quick Prompts */}
          {messages.length <= 2 && !loading && (
            <div className="px-3 py-2 bg-white border-t border-gray-100">
              <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
                Pertanyaan Cepat:
              </p>
              <div className="flex flex-wrap gap-1.5">
                {currentPrompts.map((prompt, pIdx) => (
                  <button
                    key={pIdx}
                    onClick={() => handleSend(prompt)}
                    className="text-[11px] bg-gray-100 hover:bg-blue-50 hover:text-blue-700 text-gray-700 px-2.5 py-1 rounded-full text-left transition-colors border border-gray-200 cursor-pointer"
                  >
                    {prompt}
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* Input Box */}
          <form
            onSubmit={(e) => {
              e.preventDefault();
              handleSend();
            }}
            className="p-3 bg-white border-t border-gray-200 flex items-center gap-2"
          >
            <input
              type="text"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder={loading ? 'Asisten sedang memproses...' : 'Tulis pesan ke asisten AI...'}
              disabled={loading}
              className="flex-1 px-3.5 py-2 text-sm bg-gray-50 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#4A90D9] focus:bg-white transition-all disabled:opacity-50"
            />
            <button
              type="submit"
              disabled={!input.trim() || loading}
              className="px-3.5 py-2 bg-[#1A3A5C] text-white rounded-xl text-sm font-semibold hover:bg-[#142e4a] transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center cursor-pointer"
              title="Kirim Pesan"
            >
              ➤
            </button>
          </form>
        </div>
      )}
    </div>
  );
}
