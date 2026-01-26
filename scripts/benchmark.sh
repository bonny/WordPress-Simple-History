#!/bin/bash
#
# Simple History Performance Benchmark
#
# Uses 'hey' for HTTP load testing with percentile reporting.
# Install: brew install hey
#
# Usage:
#   ./scripts/benchmark.sh           # Run benchmark
#   ./scripts/benchmark.sh --setup   # Setup clean environment first
#   ./scripts/benchmark.sh --compare # Compare with previous run
#

BASE_URL="http://localhost:8888"
RESULTS_DIR="$(dirname "$0")/../benchmarks"
REQUESTS=200
CONCURRENCY=4

# Application password for REST API auth - created automatically
REST_API_USER="admin"
REST_API_AUTH=""

setup_rest_api_auth() {
    # Delete existing benchmark password if any
    npx wp-env run cli wp user application-password delete admin benchmark 2>/dev/null || true

    # Create new one
    local pass
    pass=$(npx wp-env run cli wp user application-password create admin benchmark --porcelain 2>&1 | grep -E '^[a-zA-Z0-9]+$' | head -1)

    if [[ -n "$pass" ]]; then
        REST_API_AUTH=$(echo -n "${REST_API_USER}:${pass}" | base64)
        echo "REST API auth configured."
    else
        echo "Warning: Could not create REST API auth. Simple History API benchmark will fail."
    fi
}

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check for hey
if ! command -v hey &> /dev/null; then
    echo "Error: 'hey' is not installed. Install with: brew install hey"
    exit 1
fi

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
    echo "Warming up (20 requests per endpoint)..."
    for i in $(seq 1 20); do
        curl -s "$BASE_URL/" > /dev/null
        curl -s "$BASE_URL/wp-login.php" > /dev/null
        curl -s "$BASE_URL/?rest_route=/wp/v2/posts" > /dev/null
        curl -s -H "Authorization: Basic $REST_API_AUTH" "$BASE_URL/?rest_route=/simple-history/v1/events" > /dev/null
    done
    echo "Warmup complete."
}

run_benchmark() {
    local name="$1"
    local url="$2"
    local auth="${3:-}"

    echo ""
    echo "--- $name ---"

    # Run hey and extract key metrics
    local output
    if [[ -n "$auth" ]]; then
        output=$(hey -n $REQUESTS -c $CONCURRENCY -H "Authorization: Basic $auth" "$url" 2>&1)
    else
        output=$(hey -n $REQUESTS -c $CONCURRENCY "$url" 2>&1)
    fi

    # Extract and display key metrics
    local total=$(echo "$output" | grep "Total:" | awk '{printf "%.2f", $2}')
    local rps=$(echo "$output" | grep "Requests/sec" | awk '{printf "%.1f", $2}')
    local p50=$(echo "$output" | grep "50%%" | awk '{printf "%.0f", $3 * 1000}')
    local p90=$(echo "$output" | grep "90%%" | awk '{printf "%.0f", $3 * 1000}')
    local p99=$(echo "$output" | grep "99%%" | awk '{printf "%.0f", $3 * 1000}')
    local status_line=$(echo "$output" | grep -E "^\s*\[[0-9]+\]" | head -1)
    local status_code=$(echo "$status_line" | grep -oE '\[[0-9]+\]' | tr -d '[]')
    local status_count=$(echo "$status_line" | awk '{print $2}')

    printf "  Total:  %ss | Requests/sec: %s\n" "$total" "$rps"
    printf "  p50:  %4sms\n" "${p50:-n/a}"
    printf "  p90:  %4sms\n" "${p90:-n/a}"
    printf "  p99:  %4sms\n" "${p99:-n/a}"

    if [[ -n "$status_code" && "$status_code" != "200" ]]; then
        echo "  ⚠ HTTP $status_code ($status_count responses)"
    fi
}

run_all_benchmarks() {
    echo "========================================"
    echo "Simple History Performance Benchmark"
    echo "========================================"
    echo "Date: $(date)"
    echo "Git: $GIT_BRANCH @ $GIT_COMMIT"
    echo "Tool: hey -n $REQUESTS -c $CONCURRENCY"
    echo ""

    echo "Environment:"
    echo "  WP: $(npx wp-env run cli wp core version 2>&1 | grep -oE '^[0-9]+\.[0-9]+' | head -1 || echo 'unknown')"
    echo "  PHP: $(npx wp-env run cli php -r 'echo PHP_VERSION;' 2>&1 | grep -oE '^[0-9]+\.[0-9]+\.[0-9]+' | head -1 || echo 'unknown')"
    echo "  Theme: $(npx wp-env run cli wp theme list --status=active --field=name 2>&1 | grep -vE 'Starting|Ran|ℹ|✔|^\s*$' | head -1 || echo 'unknown')"
    echo "  Plugins: $(npx wp-env run cli wp plugin list --status=active --field=name 2>&1 | grep -vE 'Starting|Ran|ℹ|✔|^\s*$' | paste -sd ',' - || echo 'none')"

    run_benchmark "Frontend (homepage)" "${BASE_URL}/"
    run_benchmark "WP Login page" "${BASE_URL}/wp-login.php"
    run_benchmark "REST API: WP Posts (baseline)" "${BASE_URL}/?rest_route=/wp/v2/posts"
    run_benchmark "REST API: Simple History Events" "${BASE_URL}/?rest_route=/simple-history/v1/events" "$REST_API_AUTH"

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
        setup_rest_api_auth
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
        setup_rest_api_auth
        warmup
        run_all_benchmarks 2>&1 | tee "$RESULT_FILE"
        ;;
esac

echo ""
echo -e "${GREEN}Results saved to: $RESULT_FILE${NC}"
