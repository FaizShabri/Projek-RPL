import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';

export default function Onboarding() {
  const { reloadUser } = useAuth();
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    nama_lengkap: '',
    alamat_lengkap: '',
    rt_rw: '',
    nomor_telepon: ''
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      await api.post('/user/profile', formData);
      await reloadUser();
      navigate('/');
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-neutral flex items-center justify-center p-4">
      <div className="bg-white rounded-xl shadow-lg max-w-md w-full p-8">
        <h1 className="text-2xl font-bold text-primary mb-2">Lengkapi Profil Anda</h1>
        <p className="text-gray-500 mb-6 text-sm">Sebelum mulai membuat order, kami butuh beberapa informasi dasar untuk keperluan penjemputan/pengantaran laundry.</p>

        {error && (
          <div className="bg-red-50 text-red-600 p-3 rounded-md text-sm mb-4">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
            <input 
              type="text" required 
              className="w-full border border-gray-300 rounded-md p-2"
              value={formData.nama_lengkap}
              onChange={e => setFormData({...formData, nama_lengkap: e.target.value})}
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Alamat Lengkap</label>
            <textarea 
              required rows="3"
              className="w-full border border-gray-300 rounded-md p-2"
              value={formData.alamat_lengkap}
              onChange={e => setFormData({...formData, alamat_lengkap: e.target.value})}
            ></textarea>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">RT/RW</label>
              <input 
                type="text" required placeholder="001/002"
                className="w-full border border-gray-300 rounded-md p-2"
                value={formData.rt_rw}
                onChange={e => setFormData({...formData, rt_rw: e.target.value})}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">No. Telepon/WA</label>
              <input 
                type="text" required placeholder="0812..."
                className="w-full border border-gray-300 rounded-md p-2"
                value={formData.nomor_telepon}
                onChange={e => setFormData({...formData, nomor_telepon: e.target.value})}
              />
            </div>
          </div>

          <button 
            type="submit" 
            disabled={loading}
            className="w-full bg-secondary text-white rounded-md py-2 font-medium hover:bg-secondary-dark disabled:opacity-50"
          >
            {loading ? 'Menyimpan...' : 'Simpan Profil'}
          </button>
        </form>
      </div>
    </div>
  );
}
