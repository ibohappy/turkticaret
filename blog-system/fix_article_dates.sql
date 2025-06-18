-- Makale tarihlerini düzelt
-- published_at NULL olan makaleleri created_at tarihi ile güncelle

UPDATE articles 
SET published_at = created_at 
WHERE published_at IS NULL OR published_at = '' OR published_at = '0000-00-00 00:00:00';

-- Eğer created_at da NULL/boş ise şu anki zaman ile güncelle
UPDATE articles 
SET created_at = NOW(), published_at = NOW() 
WHERE created_at IS NULL OR created_at = '' OR created_at = '0000-00-00 00:00:00';

-- Veritabanını kontrol et
SELECT id, title, created_at, published_at, updated_at 
FROM articles 
ORDER BY id; 