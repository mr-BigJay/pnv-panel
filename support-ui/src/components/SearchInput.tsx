import { useState } from 'react';
import { FiSearch, FiX } from 'react-icons/fi';

interface SearchInputProps {
  onSearch: (query: string) => void;
  placeholder?: string;
}

export function SearchInput({ onSearch, placeholder = 'جستجو...' }: SearchInputProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [isFocused, setIsFocused] = useState(false);

  function clearSearch() {
    setSearchQuery('');
    onSearch('');
  }

  return (
    <div className="relative flex-1">
      <input
        type="text"
        placeholder={placeholder}
        value={searchQuery}
        onChange={(e) => {
          setSearchQuery(e.target.value);
          onSearch(e.target.value);
        }}
        onKeyDown={(e) => {
          if (e.key === 'Escape') clearSearch();
        }}
        onFocus={() => setIsFocused(true)}
        onBlur={() => setIsFocused(false)}
        className={`w-full rounded-3xl border border-[#2d2d2d] bg-[#2d2d2d] py-2 pl-10 pr-10 text-sm text-white outline-none transition-all duration-200 placeholder:text-gray-500 ${
          isFocused ? 'ring-2 ring-blue-500/50' : ''
        }`}
      />
      <FiSearch
        size={18}
        className={`pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 ${
          isFocused || searchQuery ? 'text-blue-400' : 'text-gray-500'
        }`}
      />
      {searchQuery ? (
        <button
          type="button"
          onClick={clearSearch}
          className="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-gray-400 hover:bg-gray-600 hover:text-white"
        >
          <FiX size={16} />
        </button>
      ) : null}
    </div>
  );
}
