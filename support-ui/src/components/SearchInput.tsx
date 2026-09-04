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
        className={`tg-input-field w-full rounded-full border-0 py-2.5 pl-10 pr-10 text-[13px] outline-none transition-all duration-200 ${
          isFocused ? 'ring-2 ring-[#6ab2f2]/40' : ''
        }`}
      />
      <FiSearch
        size={16}
        className={`pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 ${
          isFocused || searchQuery ? 'text-[#6ab2f2]' : 'text-[#6d8399]'
        }`}
      />
      {searchQuery ? (
        <button
          type="button"
          onClick={clearSearch}
          className="absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-[#6d8399] hover:bg-[#3d4f63] hover:text-white"
        >
          <FiX size={16} />
        </button>
      ) : null}
    </div>
  );
}
