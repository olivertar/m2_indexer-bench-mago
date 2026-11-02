# Orangecat_IndexerBenchMago

[Mago](https://askmago.com/) add-on for [Orangecat_IndexerBench](https://github.com/olivertar/m2_indexer-bench): read benchmark history,
comparisons and run reports from the admin assistant. Development and staging only, like the benchmark. MIT licence.

## Requirements and installation

- Magento ≥ 2.4.9 or Mage-OS ≥ 3.0, `mago-assistant/magento2-mago` ^1.1, `Orangecat_IndexerBench`.

```bash
composer config repositories.m2_indexer-boost vcs https://github.com/olivertar/m2_indexer-boost.git
composer config repositories.m2_indexer-bench vcs https://github.com/olivertar/m2_indexer-bench.git
composer config repositories.m2_indexer-bench-mago vcs https://github.com/olivertar/m2_indexer-bench-mago.git
composer require --dev orangecat/module-indexer-bench-mago:^0.1
bin/magento module:enable Orangecat_IndexerBench Orangecat_IndexerBenchMago
bin/magento setup:upgrade
```

The three repositories are declared because the packages are not on Packagist; Mago itself comes from Packagist.

## Tools (all read only, ACL `Orangecat_IndexerBenchMago::view`)

| Tool | Parameters | What it returns |
|---|---|---|
| `indexer_bench_history` | `indexer`, `metric` (default `wall_time_s`), `limit` (1–20, default 10) | Recent runs and the median, range and trend per label, as `indexer-bench:history` |
| `indexer_bench_compare` | `indexer`, `baseline`, `candidate` (run ID or label), `threshold` (default 10 %) | Change and verdict per metric, fingerprint differences, output `MATCH`/`DIFFER`, regressions, as `indexer-bench:compare` |
| `indexer_bench_report` | `run_id` (default: latest run) | The Markdown report of `indexer-bench:export` |

Example questions: "Compare rep-core-2026-11-02 with rep-boost-2026-11-02 for the price indexer", "Show the benchmark
history of catalog_category_product".

## Privacy and limits

- Every returned field is `PUBLIC` (metrics, labels, run IDs, dates). Run notes are not returned, except inside the
  Markdown report.
- Starting a run is not offered: it takes minutes to hours, must never run in production, and a Mago tool runs inside
  the chat request without a timeout. Runs start from the command line on a copy of the database.

## Testing

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Orangecat/IndexerBenchMago/Test/Unit
bin/magento mago:tool:verify indexer_bench_history '{"indexer":"catalog_product_price"}'
```

## Related packages

| Repository | Package | Purpose |
|---|---|---|
| [m2_indexer-boost](https://github.com/olivertar/m2_indexer-boost) | `orangecat/module-indexer-boost` | doctor, passive monitor, faster full reindexes (production) — [documentation](https://github.com/olivertar/m2_indexer-boost/tree/main/docs) |
| [m2_indexer-bench](https://github.com/olivertar/m2_indexer-bench) | `orangecat/module-indexer-bench` | benchmark (development and staging only) |
| [m2_indexer-boost-mago](https://github.com/olivertar/m2_indexer-boost-mago) | `orangecat/module-indexer-boost-mago` | Mago add-on for Indexer Boost |
| [m2_indexer-bench-mago](https://github.com/olivertar/m2_indexer-bench-mago) | `orangecat/module-indexer-bench-mago` | Mago add-on for the benchmark |

## Licence

MIT, see [LICENSE](LICENSE).
