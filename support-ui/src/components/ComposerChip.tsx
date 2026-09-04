interface ComposerChipProps {
  title: string;
  preview: string;
  onClear: () => void;
}

export function ComposerChip({ title, preview, onClear }: ComposerChipProps) {
  return (
    <div className="support-composer-chip">
      <div className="min-w-0 flex-1">
        <div className="support-composer-chip-title">{title}</div>
        <div className="support-composer-chip-preview">{preview}</div>
      </div>
      <button type="button" className="support-composer-chip-clear" onClick={onClear} aria-label="بستن">
        ×
      </button>
    </div>
  );
}
