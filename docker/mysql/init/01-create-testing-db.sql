-- Runs only on first MySQL data-dir init (fresh volume).
-- For existing volumes, create manually:
--   docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS curator_testing;"
CREATE DATABASE IF NOT EXISTS curator_testing;
