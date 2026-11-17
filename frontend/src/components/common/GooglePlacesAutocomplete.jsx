import { useEffect, useRef, useState } from 'react';

/**
 * GooglePlacesAutocomplete component
 * Menggunakan Google Maps Places API untuk pencarian alamat dan auto-populate lat/lng.
 * Memiliki gracefully fallback jika API key belum diisi atau gagal memuat.
 */
export default function GooglePlacesAutocomplete({ onSelectPlace, initialAddress = '', initialLat = null, initialLng = null }) {
  const inputRef = useRef(null);
  const [address, setAddress] = useState(initialAddress);
  const [lat, setLat] = useState(initialLat);
  const [lng, setLng] = useState(initialLng);
  const [scriptLoaded, setScriptLoaded] = useState(false);
  const [useManualCoordinates, setUseManualCoordinates] = useState(false);

  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

  useEffect(() => {
    setAddress(initialAddress);
    setLat(initialLat);
    setLng(initialLng);
  }, [initialAddress, initialLat, initialLng]);

  useEffect(() => {
    if (!apiKey || apiKey === 'your-google-maps-api-key') {
      setUseManualCoordinates(true);
      return;
    }

    if (window.google && window.google.maps && window.google.maps.places) {
      setScriptLoaded(true);
      return;
    }

    const existingScript = document.getElementById('google-maps-script');
    if (!existingScript) {
      const script = document.createElement('script');
      script.id = 'google-maps-script';
      script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
      script.async = true;
      script.defer = true;
      script.onload = () => setScriptLoaded(true);
      script.onerror = () => setUseManualCoordinates(true);
      document.head.appendChild(script);
    } else {
      existingScript.addEventListener('load', () => setScriptLoaded(true));
    }
  }, [apiKey]);

  useEffect(() => {
    if (scriptLoaded && window.google && inputRef.current) {
      const autocomplete = new window.google.maps.places.Autocomplete(inputRef.current, {
        componentRestrictions: { country: 'id' }, // Khusus wilayah Indonesia
        fields: ['formatted_address', 'geometry', 'name'],
      });

      autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        if (place.geometry && place.geometry.location) {
          const selectedAddress = place.formatted_address || place.name;
          const selectedLat = place.geometry.location.lat();
          const selectedLng = place.geometry.location.lng();

          setAddress(selectedAddress);
          setLat(selectedLat);
          setLng(selectedLng);

          onSelectPlace({
            alamat: selectedAddress,
            latitude: selectedLat,
            longitude: selectedLng,
          });
        }
      });
    }
  }, [scriptLoaded, onSelectPlace]);

  const handleManualAddressChange = (e) => {
    const val = e.target.value;
    setAddress(val);
    onSelectPlace({
      alamat: val,
      latitude: lat,
      longitude: lng,
    });
  };

  const handleManualCoordChange = (newLat, newLng) => {
    setLat(newLat);
    setLng(newLng);
    onSelectPlace({
      alamat: address,
      latitude: newLat,
      longitude: newLng,
    });
  };

  const getCurrentLocation = () => {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const currentLat = position.coords.latitude;
          const currentLng = position.coords.longitude;
          setLat(currentLat);
          setLng(currentLng);
          onSelectPlace({
            alamat: address,
            latitude: currentLat,
            longitude: currentLng,
          });
        },
        (error) => {
          alert('Gagal mendapatkan lokasi saat ini: ' + error.message);
        }
      );
    } else {
      alert('Browser Anda tidak mendukung Geolocation.');
    }
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          Alamat Lengkap Toko (Google Places Autocomplete)
        </label>
        <div className="relative">
          <input
            ref={inputRef}
            type="text"
            required
            placeholder="Ketik nama jalan, komplek, atau area toko..."
            value={address}
            onChange={handleManualAddressChange}
            className="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-secondary focus:border-secondary"
          />
          <button
            type="button"
            onClick={getCurrentLocation}
            title="Gunakan lokasi GPS saat ini"
            className="absolute right-2 top-2 px-2.5 py-1 text-xs bg-secondary/10 hover:bg-secondary/20 text-secondary font-medium rounded-md transition-colors"
          >
            📍 GPS
          </button>
        </div>
      </div>

      {/* Tampilan Koordinat Lat/Lng */}
      <div className="grid grid-cols-2 gap-3 bg-neutral p-3 rounded-lg border border-gray-200">
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Latitude</label>
          <input
            type="number"
            step="any"
            value={lat || ''}
            onChange={(e) => handleManualCoordChange(parseFloat(e.target.value) || null, lng)}
            placeholder="-6.200000"
            className="w-full bg-white border border-gray-300 rounded px-2.5 py-1 text-xs"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">Longitude</label>
          <input
            type="number"
            step="any"
            value={lng || ''}
            onChange={(e) => handleManualCoordChange(lat, parseFloat(e.target.value) || null)}
            placeholder="106.816666"
            className="w-full bg-white border border-gray-300 rounded px-2.5 py-1 text-xs"
          />
        </div>
      </div>
      {lat && lng && (
        <p className="text-xs text-tertiary-dark font-medium">
          ✓ Pin Lokasi koordinat terdeteksi ({lat.toFixed(6)}, {lng.toFixed(6)})
        </p>
      )}
    </div>
  );
}
