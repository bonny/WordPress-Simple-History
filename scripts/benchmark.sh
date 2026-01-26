#!/bin/bash
#
# Simple History Performance Benchmark
#
# Usage:
#   ./scripts/benchmark.sh           # Run benchmark
#   ./scripts/benchmark.sh --setup   # Setup clean environment first
#   ./scripts/benchmark.sh --compare # Compare with previous run
#

BASE_URL="http://localhost:8888"
RESULTS_DIR="$(dirname "$0")/../benchmarks"
REQUESTS=50
CONCURRENCY=2

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Create results directory
mkdir -p "$RESULTS_DIR"

# Get current git info
GIT_COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
GIT_BRANCH=$(git branch --show-current 2>/dev/null || echo "unknown")
TIMESTAMP=$(date +%Y-%m-%d_%H-%M-%S)
RESULT_FILE="$RESULTS_DIR/benchmark_${TIMESTAMP}_${GIT_BRANCH}_${GIT_COMMIT}.txt"

setup_clean_environment() {
    echo -e "${YELLOW}Setting up clean benchmark environment...${NC}"
    npx wp-env run cli wp plugin deactivate --all 2>/dev/null || true
    npx wp-env run cli wp plugin activate WordPress-Simple-History
    npx wp-env run cli wp theme activate twentytwentyfive
    echo -e "${GREEN}Environment ready: Only Simple History active${NC}"
}

warmup() {
    echo "Warming up OPcache..."
    for i in 1 2 3 4 5; do
        curl -s "$BASE_URL/" > /dev/null
    done
    echo "Warmup complete."
}

run_benchmark() {
    local name="$1"
    local url="$2"

    echo ""
    echo "--- $name ---"
    echo "URL: $url"

    # Run ab and extract key lines
    local output
    output=$(ab -n $REQUESTS -c $CONCURRENCY "$url" 2>&1)

    echo "$output" | grep "Time taken for tests" || true
    echo "$output" | grep "Requests per second" || true
    echo "$output" | grep "Time per request" | head -1 || true
    echo "$output" | grep "Failed requests" || true
}

run_all_benchmarks() {
    echo "========================================"
    echo "Simple History Performance Benchmark"
    echo "========================================"
    echo "Date: $(date)"
    echo "Git: $GIT_BRANCH @ $GIT_COMMIT"
    echo "Requests: $REQUESTS, Concurrency: $CONCURRENCY"
    echo ""

    echo "Environment:"
    echo "WP: $(npx wp-env run cli wp core version 2>&1 | grep -oE '^[0-9]+\.[0-9]+' | head -1 || echo 'unknown')"
    echo "PHP: $(npx wp-env run cli php -r 'echo PHP_VERSION;' 2>&1 | grep -oE '^[0-9]+\.[0-9]+\.[0-9]+' | head -1 || echo 'unknown')"
    echo "Theme: $(npx wp-env run cli wp theme list --status=active --field=name 2>&1 | grep -vE 'Starting|Ran|ℹ|✔|^\s*$' | head -1 || echo 'unknown')"
    echo "Plugins: $(npx wp-env run cli wp plugin list --status=active --field=name 2>&1 | grep -vE 'Starting|Ran|ℹ|✔|^\s*$' | paste -sd ',' - || echo 'none')"

    run_benchmark "Frontend (homepage)" "${BASE_URL}/"
    run_benchmark "WP Login page" "${BASE_URL}/wp-login.php"
    run_benchmark "REST API: Simple History Events" "${BASE_URL}/wp-json/simple-history/v1/events"
    run_benchmark "REST API: WP Posts (baseline)" "${BASE_URL}/wp-json/wp/v2/posts"

    echo ""
    echo "========================================"
}

compare_results() {
    echo -e "${YELLOW}Recent benchmark results:${NC}"
    echo ""
    ls -lt "$RESULTS_DIR"/*.txt 2>/dev/null | head -5 || echo "No results yet"
    echo ""
    echo "Files are in: $RESULTS_DIR/"
}

# Parse arguments
case "${1:-}" in
    --setup)
        setup_clean_environment
        warmup
        run_all_benchmarks 2>&1 | tee "$RESULT_FILE"
        ;;
    --compare)
        compare_results
        exit 0
        ;;
    --help|-h)
        echo "Usage: $0 [--setup|--compare|--help]"
        echo ""
        echo "  (no args)  Run benchmark with current environment"
        echo "  --setup    Deactivate other plugins first, then benchmark"
        echo "  --compare  List recent benchmark results"
        exit 0
        ;;
    *)
        warmup
        run_all_benchmarks 2>&1 | tee "$RESULT_FILE"
        ;;
esac

echo ""
echo -e "${GREEN}Results saved to: $RESULT_FILE${NC}"
