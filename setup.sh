#!/bin/bash
#===============================================================================
# Monstein API - One-Command Setup Script
#===============================================================================
# Usage: ./setup.sh [OPTIONS]
#
# Options:
#   --port PORT      Set API port (default: 8080)
#   --db-port PORT   Set database port (default: 3306)
#   --scale N        Number of app instances (default: 2)
#   --dev            Start with development tools (Adminer)
#   --clean          Remove existing containers before starting
#   --help           Show this help
#
# Examples:
#   ./setup.sh                    # Default setup
#   ./setup.sh --port 80          # Run on port 80
#   ./setup.sh --scale 3 --dev    # 3 instances with Adminer
#===============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Default values
LB_PORT=${LB_PORT:-8080}
DB_PORT=${DB_PORT:-3306}
SCALE=${SCALE:-2}
DEV_MODE=false
CLEAN=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --port)
            LB_PORT="$2"
            shift 2
            ;;
        --db-port)
            DB_PORT="$2"
            shift 2
            ;;
        --scale)
            SCALE="$2"
            shift 2
            ;;
        --dev)
            DEV_MODE=true
            shift
            ;;
        --clean)
            CLEAN=true
            shift
            ;;
        --help)
            head -25 "$0" | tail -20
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo ""
echo -e "${CYAN}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║           ${GREEN}Monstein API - Docker Setup${CYAN}                        ║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""

#-------------------------------------------------------------------------------
# Step 1: Check prerequisites
#-------------------------------------------------------------------------------
echo -e "${BLUE}[1/6]${NC} Checking prerequisites..."

if ! command -v docker &> /dev/null; then
    echo -e "${RED}✗ Docker is not installed. Please install Docker first.${NC}"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}✗ Docker Compose is not installed. Please install Docker Compose first.${NC}"
    exit 1
fi

if ! docker info &> /dev/null; then
    echo -e "${RED}✗ Docker daemon is not running. Please start Docker.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Docker and Docker Compose are available${NC}"

#-------------------------------------------------------------------------------
# Step 2: Clean up if requested
#-------------------------------------------------------------------------------
if [ "$CLEAN" = true ]; then
    echo -e "${BLUE}[2/6]${NC} Cleaning up existing containers..."
    docker-compose down -v --remove-orphans 2>/dev/null || true
    echo -e "${GREEN}✓ Cleanup complete${NC}"
else
    echo -e "${BLUE}[2/6]${NC} Skipping cleanup (use --clean to remove existing containers)"
fi

#-------------------------------------------------------------------------------
# Step 3: Create environment file
#-------------------------------------------------------------------------------
echo -e "${BLUE}[3/6]${NC} Creating environment configuration..."

# Generate secure JWT secret
JWT_SECRET=$(openssl rand -base64 32 2>/dev/null || echo "docker-jwt-secret-$(date +%s)")

cat > .env << EOF
# Monstein API - Docker Configuration
# Generated: $(date)

# Application
APP_ENV=production
APP_DEBUG=false

# Ports
LB_PORT=${LB_PORT}
LB_SSL_PORT=8443
DB_EXTERNAL_PORT=${DB_PORT}
ADMINER_PORT=8081

# Database
DB_DRIVER=mysql
DB_NAME=monstein
DB_USER=monstein
DB_PASS=monstein_$(openssl rand -hex 8 2>/dev/null || echo "secret123")
DB_ROOT_PASS=root_$(openssl rand -hex 8 2>/dev/null || echo "rootpass")
DB_CHARSET=utf8mb4

# JWT
JWT_SECRET=${JWT_SECRET}
JWT_EXPIRES=30
JWT_ALGORITHM=HS256

# CORS
CORS_ORIGIN=*

# Logging
LOG_LEVEL=INFO

# Rate Limiting
RATE_LIMIT_MAX=100
RATE_LIMIT_WINDOW=60

# Trusted Proxies (Docker network)
TRUSTED_PROXIES=172.16.0.0/12,10.0.0.0/8,127.0.0.1

# File Uploads
FILE_STORAGE_PATH=/app/storage/uploads
FILE_BASE_URL=http://localhost:${LB_PORT}

# Usage Tracking
USAGE_TRACKER_ENABLED=true
USAGE_TRACKER_DRIVER=database
USAGE_TRACKER_SAMPLE_RATE=100
EOF

echo -e "${GREEN}✓ Environment file created${NC}"

#-------------------------------------------------------------------------------
# Step 4: Build Docker images
#-------------------------------------------------------------------------------
echo -e "${BLUE}[4/6]${NC} Building Docker images (this may take a few minutes)..."
docker-compose build --quiet 2>&1 | grep -v "^$" || true
echo -e "${GREEN}✓ Docker images built${NC}"

#-------------------------------------------------------------------------------
# Step 5: Start services
#-------------------------------------------------------------------------------
echo -e "${BLUE}[5/6]${NC} Starting services..."

if [ "$DEV_MODE" = true ]; then
    docker-compose --profile dev up -d --scale app=$SCALE 2>&1 | grep -v "deploy sub-keys" || true
else
    docker-compose up -d --scale app=$SCALE 2>&1 | grep -v "deploy sub-keys" || true
fi

echo -e "${GREEN}✓ Services started${NC}"

#-------------------------------------------------------------------------------
# Step 6: Wait for services and setup database
#-------------------------------------------------------------------------------
echo -e "${BLUE}[6/6]${NC} Waiting for services to be ready..."

# Wait for database
echo -n "  Waiting for database"
for i in {1..30}; do
    if docker-compose exec -T db mariadb -umonstein -p"$(grep DB_PASS .env | cut -d= -f2)" -e "SELECT 1" &>/dev/null; then
        echo -e " ${GREEN}✓${NC}"
        break
    fi
    echo -n "."
    sleep 2
done

# Source the .env file
source .env

# Create database tables and demo user from SQL script
echo -n "  Creating database tables and demo user"
docker-compose exec -T db mariadb -umonstein -p"$DB_PASS" monstein < "${SCRIPT_DIR}/database/db_script.sql" 2>/dev/null
echo -e " ${GREEN}✓${NC}"

# Wait for API
echo -n "  Waiting for API"
for i in {1..20}; do
    if curl -s "http://localhost:${LB_PORT}/health" | grep -q "healthy" 2>/dev/null; then
        echo -e " ${GREEN}✓${NC}"
        break
    fi
    echo -n "."
    sleep 2
done

#-------------------------------------------------------------------------------
# Display results
#-------------------------------------------------------------------------------
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║               Setup Complete! 🚀                             ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${CYAN}Access URLs:${NC}"
echo -e "  ${YELLOW}API:${NC}          http://localhost:${LB_PORT}"
echo -e "  ${YELLOW}Health:${NC}       http://localhost:${LB_PORT}/health"
echo -e "  ${YELLOW}LB Health:${NC}    http://localhost:${LB_PORT}/lb-health"
if [ "$DEV_MODE" = true ]; then
echo -e "  ${YELLOW}Adminer:${NC}      http://localhost:8081"
fi
echo ""
echo -e "${CYAN}Demo Credentials:${NC}"
echo -e "  ${YELLOW}Username:${NC}     demo"
echo -e "  ${YELLOW}Password:${NC}     demo123"
echo ""
echo -e "${CYAN}Quick Test:${NC}"
echo -e "  ${YELLOW}# Get JWT token${NC}"
echo -e "  curl -X POST http://localhost:${LB_PORT}/issueToken \\"
echo -e "    -H 'Content-Type: application/json' \\"
echo -e "    -d '{\"username\":\"demo\",\"password\":\"demo123\"}'"
echo ""
echo -e "  ${YELLOW}# Upload a file${NC}"
echo -e "  curl -X POST http://localhost:${LB_PORT}/files \\"
echo -e "    -H 'Authorization: Bearer YOUR_TOKEN' \\"
echo -e "    -F 'file=@/path/to/file.jpg'"
echo ""
echo -e "  ${YELLOW}# View usage stats${NC}"
echo -e "  curl http://localhost:${LB_PORT}/usage/stats \\"
echo -e "    -H 'Authorization: Bearer YOUR_TOKEN'"
echo ""
echo -e "${CYAN}Container Status:${NC}"
docker-compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null | grep -v "deploy sub-keys" || docker-compose ps
echo ""
echo -e "${CYAN}Useful Commands:${NC}"
echo -e "  ${YELLOW}make logs${NC}         - View logs"
echo -e "  ${YELLOW}make down${NC}         - Stop services"
echo -e "  ${YELLOW}make scale N=3${NC}    - Scale to 3 instances"
echo -e "  ${YELLOW}make shell${NC}        - Open app shell"
echo ""
