#!/bin/bash

set -e

# Welcome to the OpnForm environment setup script!

# Paths to the environment files
ENV_FILE="api/.env"
CLIENT_ENV_FILE="client/.env"

# Paths to the environment templates
ENV_EXAMPLE="api/.env.example"
CLIENT_ENV_EXAMPLE="client/.env.example"

# Check for Docker-specific environment settings
USE_DOCKER_ENV=false
PUBLIC_URL=""
PUBLIC_URL_PROVIDED=false
while [[ "$#" -gt 0 ]]; do
  case "$1" in
    --docker)
      USE_DOCKER_ENV=true
      ENV_EXAMPLE="api/.env.docker"
      CLIENT_ENV_EXAMPLE="client/.env.docker"
      ;;
    --public-url)
      PUBLIC_URL_PROVIDED=true
      if [[ "$#" -lt 2 ]]; then
        echo "Missing value for --public-url." >&2
        exit 1
      fi
      PUBLIC_URL="$2"
      shift
      ;;
    --public-url=*)
      PUBLIC_URL_PROVIDED=true
      PUBLIC_URL="${1#*=}"
      ;;
    *)
      echo "Unknown parameter: $1" >&2
      exit 1
      ;;
  esac
  shift
done

if [[ "$PUBLIC_URL_PROVIDED" == true && -z "$PUBLIC_URL" ]]; then
  echo "--public-url cannot be empty." >&2
  exit 1
fi

if [[ "$PUBLIC_URL_PROVIDED" == true && "$USE_DOCKER_ENV" != true ]]; then
  echo "--public-url can only be used with --docker." >&2
  exit 1
fi

normalize_public_url() {
  local url="$1"
  local authority
  local port=""

  while [[ "$url" == */ ]]; do
    url="${url%/}"
  done

  case "$url" in
    http://*|https://*) ;;
    *)
      echo "The public URL must start with http:// or https://." >&2
      return 1
      ;;
  esac

  authority="${url#*://}"
  if [[ "$authority" =~ ^\[[0-9A-Fa-f:.]+\](:([0-9]+))?$ ]]; then
    port="${BASH_REMATCH[2]}"
  elif [[ "$authority" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?(:([0-9]+))?$ ]]; then
    port="${BASH_REMATCH[3]}"
  else
    echo "The public URL must contain a valid hostname or IP address and no path, query, fragment, or credentials." >&2
    return 1
  fi

  if [[ -n "$port" ]] && (( 10#$port < 1 || 10#$port > 65535 )); then
    echo "The public URL port must be between 1 and 65535." >&2
    return 1
  fi

  printf '%s' "$url"
}

if [[ -n "$PUBLIC_URL" ]]; then
  PUBLIC_URL="$(normalize_public_url "$PUBLIC_URL")"
fi

if [[ "$USE_DOCKER_ENV" == true ]]; then
  echo "OpnForm setup detected the --docker flag. Preparing Docker-specific environment..."
fi

# Function to generate a random string for secrets
generate_secret() {
  LC_ALL=C tr -dc A-Za-z0-9 </dev/urandom | head -c 40 ; echo ''
}

# Function to generate a base64-encoded 32-byte string for keys
generate_base64_key() {
  openssl rand -base64 32
}

# Function to set or update an environment variable within a file
set_env_value() {
  local file=$1
  local key=$2
  local value=$3
  local delimiter="|"

  if grep -q "^$key=" "$file"; then
    # Use different sed syntax based on the operating system
    if [[ "$OSTYPE" == "darwin"* ]]; then
      # macOS uses BSD sed, which requires an argument for -i
      sed -i '' "s${delimiter}^$key=.*${delimiter}$key=$value${delimiter}" "$file"
    else
      # Linux uses GNU sed, which does not require an argument for -i
      sed -i "s${delimiter}^$key=.*${delimiter}$key=$value${delimiter}" "$file"
    fi
  else
    # Append a newline and the new key-value pair
    echo -e "\n$key=$value" >> "$file"
  fi
}

get_env_value() {
  local file=$1
  local key=$2

  if [[ ! -f "$file" ]]; then
    return 0
  fi

  grep "^$key=" "$file" | tail -n 1 | cut -d= -f2- || true
}

# Check if the main .env file exists
if [ -f "$ENV_FILE" ]; then
  echo "OpnForm's main .env file is already in place. Preserving its existing secrets."
else
  echo "Creating OpnForm's main .env file from the template..."
  cp "$ENV_EXAMPLE" "$ENV_FILE"
fi

# Secure your OpnForm instance with a unique APP_KEY
if [[ -z "$(get_env_value "$ENV_FILE" "APP_KEY")" ]]; then
  APP_KEY=$(generate_base64_key)
  set_env_value "$ENV_FILE" "APP_KEY" "base64:$APP_KEY"
fi

# Generate a JWT_SECRET to sign your tokens
if [[ -z "$(get_env_value "$ENV_FILE" "JWT_SECRET")" ]]; then
  JWT_SECRET=$(generate_secret)
  set_env_value "$ENV_FILE" "JWT_SECRET" "$JWT_SECRET"
fi

# Generate or reuse the shared secret for the client
SHARED_SECRET="$(get_env_value "$ENV_FILE" "FRONT_API_SECRET")"
if [[ -z "$SHARED_SECRET" ]]; then
  SHARED_SECRET="$(get_env_value "$CLIENT_ENV_FILE" "NUXT_API_SECRET")"
fi
if [[ -z "$SHARED_SECRET" ]]; then
  SHARED_SECRET=$(generate_secret)
fi
set_env_value "$ENV_FILE" "FRONT_API_SECRET" "$SHARED_SECRET"

if [[ "$USE_DOCKER_ENV" == true && -z "$(get_env_value "$ENV_FILE" "FRONT_URL")" ]]; then
  APP_URL_VALUE="$(get_env_value "$ENV_FILE" "APP_URL")"
  set_env_value "$ENV_FILE" "FRONT_URL" "${APP_URL_VALUE:-http://localhost}"
fi

# Check if the client .env file exists
if [ -f "$CLIENT_ENV_FILE" ]; then
  echo "OpnForm's client .env file is already configured. Preserving its existing values."
else
  echo "Creating OpnForm's client .env file from the template..."
  cp "$CLIENT_ENV_EXAMPLE" "$CLIENT_ENV_FILE"
fi

if [[ -z "$(get_env_value "$CLIENT_ENV_FILE" "NUXT_API_SECRET")" ]]; then
  set_env_value "$CLIENT_ENV_FILE" "NUXT_API_SECRET" "$SHARED_SECRET"
fi

if [[ -n "$PUBLIC_URL" ]]; then
  set_env_value "$ENV_FILE" "APP_URL" "$PUBLIC_URL"
  set_env_value "$ENV_FILE" "FRONT_URL" "$PUBLIC_URL"
  set_env_value "$CLIENT_ENV_FILE" "NUXT_PUBLIC_APP_URL" "$PUBLIC_URL"
  set_env_value "$CLIENT_ENV_FILE" "NUXT_PUBLIC_API_BASE" "$PUBLIC_URL/api"
  echo "Configured OpnForm's public URL as $PUBLIC_URL."
elif [[ "$USE_DOCKER_ENV" == true && "$(get_env_value "$ENV_FILE" "APP_URL")" == "http://localhost" ]]; then
  echo "Warning: APP_URL still points to localhost. Before production use, rerun with --public-url https://forms.example.com." >&2
fi

echo "✅ OpnForm environment setup is now complete. Enjoy building your forms!"
