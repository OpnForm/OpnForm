#!/bin/bash

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

# ASCII Art
echo -e "${BLUE}"
cat << "EOF"
  ____              ______                    
 / __ \____  ____  / ____/___  _________ ___ 
/ / / / __ \/ __ \/ /_  / __ \/ ___/ __ `__ \
/ /_/ / /_/ / / / / __/ / /_/ / /  / / / / / /
\____/ .___/_/ /_/_/    \____/_/  /_/ /_/ /_/ 
    /_/                                       
EOF
echo -e "${NC}"

# Default values
DEV_MODE=false
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Parse command line arguments
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --dev) DEV_MODE=true ;;
        *) echo "Unknown parameter: $1"; exit 1 ;;
    esac
    shift
done

cd "$PROJECT_ROOT"

echo -e "${BLUE}Starting OpnForm Docker setup...${NC}"

# Run the environment setup script with --docker flag (only for production)
if [ "$DEV_MODE" = false ]; then
    echo -e "${GREEN}Setting up environment files...${NC}"
    bash "$SCRIPT_DIR/setup-env.sh" --docker
else
    echo -e "${GREEN}Development mode - skipping .env generation (using docker-compose environment variables)${NC}"
fi

# Determine which compose file to use
if [ "$DEV_MODE" = true ]; then
    echo -e "${YELLOW}Development mode enabled - using minimal setup with docker-compose.dev.yml${NC}"
    COMPOSE_FILE="docker-compose.dev.yml"
else
    echo -e "${YELLOW}Production mode - using docker-compose.yml${NC}"
    COMPOSE_FILE="docker-compose.yml"
fi

# Check for override file and build compose command
COMPOSE_CMD="docker compose -f $COMPOSE_FILE"
if [ -f "docker-compose.override.yml" ]; then
    echo -e "${BLUE}Found docker-compose.override.yml - including local overrides${NC}"
    COMPOSE_CMD="$COMPOSE_CMD -f docker-compose.override.yml"
fi

# Start Docker containers
echo -e "${GREEN}Starting Docker containers...${NC}"
$COMPOSE_CMD up -d

# Display access instructions
if [ "$DEV_MODE" = true ]; then
    echo -e "${BLUE}Development environment setup complete!${NC}"
    echo -e "${YELLOW}Please wait for the frontend to finish building (this may take a few minutes)${NC}"
    echo -e "${GREEN}Then visit: http://localhost:3000/setup${NC}"
else
    echo -e "${BLUE}Production environment setup complete!${NC}"
    echo -e "${YELLOW}Please wait a moment for all services to start${NC}"
    echo -e "${GREEN}Then visit: http://localhost${NC}"
fi
