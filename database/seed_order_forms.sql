-- Bons de commande historiques (2020–2026)
-- Prérequis : copier les PDFs sources dans storage/order_forms/
--   cp resources_publique/resources/files/prices/*.pdf storage/order_forms/
-- ou via le script database/copy_order_forms.sh

INSERT INTO `order_forms` (`year`, `label`, `filename`, `uploaded_at`) VALUES
(2020, NULL,  '2020_prices.pdf',    '2020-01-01 00:00:00'),
(2021, NULL,  '2021_prices.pdf',    '2021-01-01 00:00:00'),
(2021, 'V2',  '2021_prices_V2.pdf', '2021-06-01 00:00:00'),
(2022, NULL,  '2022_prices.pdf',    '2022-01-01 00:00:00'),
(2023, NULL,  '2023_prices.pdf',    '2023-01-01 00:00:00'),
(2024, NULL,  '2024_prices.pdf',    '2024-01-01 00:00:00'),
(2025, NULL,  '2025_prices.pdf',    '2025-01-01 00:00:00'),
(2026, NULL,  '2026_prices.pdf',    '2026-01-01 00:00:00');
