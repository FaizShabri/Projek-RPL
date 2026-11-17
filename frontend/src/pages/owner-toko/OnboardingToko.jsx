import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import GooglePlacesAutocomplete from '../../components/common/GooglePlacesAutocomplete';

export default function OnboardingToko() {
  const navigate = useNavigate();
  const [nama, setNama] = useState('');
  const [locationData, setLocationData] = useState({
    alamat: '',
    latitude: null,
    longitude: null,
  });
  const [fileLegalitas, setFileLegalitas] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const formData = new FormData();
      formData.append('nama', nama);
      formData.append('alamat', locationData.alamat);
      if (locationData.latitude) formData.append('latitude', locationData.latitude);
      if (locationData.longitude) formData.append('longitude', locationData.longitude);
      if (fileLegalitas) formData.append('dokumen_legalitas', fileLegalitas);

      const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
      const token = localStorage.getItem('auth_token');
      const activeRole = localStorage.getItem('active_role') || 'owner_toko';

      const response = await fetch(`${API_BASE_URL}/owner/tokos`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          Authorization: `Bearer ${token}`,
          'X-Active-Role': activeRole,
        },
        body: formData,
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Gagal mendaftarkan toko.');
      }

      alert('Toko Anda berhasil diajukan! Status saat ini: Submitted (Menunggu persetujuan Admin).');
      navigate('/owner-toko');
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-2xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-200 p-8 my-6">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-primary">Daftarkan Toko Laundry Baru</h1>
        <p className="text-sm text-gray-500 mt-1">
          Lengkapi data profil toko Anda. Toko akan ditinjau oleh platform admin sebelum tampil di marketplace.
        </p>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-6">
          {error}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Nama Toko */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Nama Toko / Laundry <span className="text-red-500">*</span>
          </label>
          <input
            type="text"
            required
            placeholder="Contoh: Pusat Laundry Cabang Tebet"
            value={nama}
            onChange={(e) => setNama(e.target.value)}
            className="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary"
          />
        </div>

        {/* Alamat & Google Maps Autocomplete */}
        <GooglePlacesAutocomplete
          initialAddress={locationData.alamat}
          initialLat={locationData.latitude}
          initialLng={locationData.longitude}
          onSelectPlace={setLocationData}
        />

        {/* Upload Dokumen Legalitas */}
        <div className="border-t border-gray-100 pt-5">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Dokumen Legalitas Toko (NIB / KTP / Izin Usaha)
          </label>
          <p className="text-xs text-gray-500 mb-2">
            💡 <em>Opsional saat pendaftaran awal</em>, namun <strong>wajib diunggah sebelum settlement pertama</strong>.
          </p>
          <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png"
            onChange={(e) => setFileLegalitas(e.target.files[0] || null)}
            className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-secondary/10 file:text-secondary hover:file:bg-secondary/20 cursor-pointer"
          />
          {fileLegalitas && (
            <p className="text-xs text-gray-600 mt-1">File terpilih: {fileLegalitas.name} ({(fileLegalitas.size / 1024 / 1024).toFixed(2)} MB)</p>
          )}
        </div>

        {/* Submit Button */}
        <div className="flex gap-3 pt-4">
          <button
            type="button"
            onClick={() => navigate('/owner-toko')}
            className="px-5 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors"
          >
            Batal
          </button>
          <button
            type="submit"
            disabled={loading || !nama || !locationData.alamat}
            className="flex-1 bg-primary hover:bg-primary-dark text-white text-sm font-medium py-2.5 px-4 rounded-lg shadow transition-colors disabled:opacity-50"
          >
            {loading ? 'Mengajukan Toko...' : 'Ajukan Toko (Submit)'}
          </button>
        </div>
      </form>
    </div>
  );
}
