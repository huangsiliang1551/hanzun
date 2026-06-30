import { Image } from 'antd';
import { resolveAssetUrl } from '@/utils/media';

export default function ContentCoverThumb({ record, size = 64, label = '封面' }) {
  const src = resolveAssetUrl(record?.cover_thumb_url || record?.cover_file_path || '');

  if (!src) {
    return <div className="content-cover-thumb content-cover-thumb-empty">{label}</div>;
  }

  return (
    <Image
      src={src}
      alt={record?.title_zh || record?.name_zh || label}
      width={size}
      height={size}
      preview={false}
      className="content-cover-thumb"
      style={{ objectFit: 'cover' }}
    />
  );
}
