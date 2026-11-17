import React from 'react';

/**
 * Komponen RatingStars untuk menampilkan rating bintang (1 - 5).
 * Mendukung rating desimal dengan visualisasi bintang yang rapi.
 */
export default function RatingStars({ rating = 0, size = 'sm', showScore = true, reviewCount = null }) {
  const numericRating = Math.max(0, Math.min(5, Number(rating) || 0));
  
  const sizeClasses = {
    xs: 'text-xs',
    sm: 'text-sm',
    md: 'text-base',
    lg: 'text-xl',
  };

  const starSize = sizeClasses[size] || sizeClasses.sm;

  return (
    <div className="inline-flex items-center gap-1.5">
      <div className={`flex items-center text-amber-400 ${starSize}`}>
        {[1, 2, 3, 4, 5].map((star) => {
          const filled = numericRating >= star;
          const half = !filled && numericRating >= star - 0.5;

          return (
            <span key={star} className="leading-none select-none">
              {filled ? '★' : half ? '★' : '☆'}
            </span>
          );
        })}
      </div>

      {showScore && (
        <span className="font-bold text-gray-800 text-xs sm:text-sm ml-0.5">
          {numericRating.toFixed(1)}
        </span>
      )}

      {reviewCount !== null && (
        <span className="text-gray-400 text-xs">
          ({reviewCount} ulasan)
        </span>
      )}
    </div>
  );
}
