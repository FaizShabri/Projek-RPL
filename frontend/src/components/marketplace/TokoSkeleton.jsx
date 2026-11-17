import React from 'react';

/**
 * TokoSkeleton: Skeleton loading dengan animasi pulse/shimmer
 * Mensimulasikan struktur kartu toko laundry pada listing.
 */
export default function TokoSkeleton({ count = 6 }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      {Array.from({ length: count }).map((_, index) => (
        <div
          key={index}
          className="bg-white rounded-2xl border border-gray-200/80 p-5 shadow-xs animate-pulse flex flex-col justify-between space-y-4"
        >
          {/* Header Card */}
          <div className="space-y-2.5">
            <div className="flex items-start justify-between gap-3">
              <div className="h-5 bg-gray-200 rounded-md w-3/4" />
              <div className="h-5 bg-gray-200 rounded-full w-16 shrink-0" />
            </div>

            {/* Rating & Distance */}
            <div className="flex items-center gap-2 pt-1">
              <div className="h-4 bg-gray-200 rounded w-24" />
              <div className="h-4 bg-gray-200 rounded w-16" />
            </div>

            {/* Address */}
            <div className="space-y-1.5 pt-1">
              <div className="h-3.5 bg-gray-100 rounded w-full" />
              <div className="h-3.5 bg-gray-100 rounded w-2/3" />
            </div>
          </div>

          {/* Tarifs Preview */}
          <div className="border-t border-gray-100 pt-3 space-y-2">
            <div className="h-3 bg-gray-200 rounded w-1/3 mb-2" />
            <div className="flex justify-between items-center">
              <div className="h-3 bg-gray-100 rounded w-1/2" />
              <div className="h-3 bg-gray-200 rounded w-1/4" />
            </div>
            <div className="flex justify-between items-center">
              <div className="h-3 bg-gray-100 rounded w-2/5" />
              <div className="h-3 bg-gray-200 rounded w-1/4" />
            </div>
          </div>

          {/* Card Footer Button */}
          <div className="pt-2">
            <div className="h-9 bg-gray-200 rounded-xl w-full" />
          </div>
        </div>
      ))}
    </div>
  );
}
