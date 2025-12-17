FROM node:20-bookworm

# Install minimal system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    php-cli \
    sudo \
    && rm -rf /var/lib/apt/lists/*

# Install Claude Code CLI globally
RUN npm install -g @anthropic-ai/claude-code

# Configure node user (already exists in node:20 image with UID 1000)
# Give node user sudo access and create required directories
RUN echo "node ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers && \
    mkdir -p /workspace /home/node/.claude && \
    chown -R node:node /workspace /home/node/.claude

# Create entrypoint script inline to avoid permission issues
RUN printf '#!/bin/bash\nsudo chown -R $(id -u):$(id -g) /home/node/.claude 2>/dev/null || true\nexec "$@"\n' > /entrypoint.sh && \
    chmod +x /entrypoint.sh

# Set environment variables
ENV DEVCONTAINER=true
ENV TERM=xterm-256color
ENV COLORTERM=truecolor

# Switch to non-root user
USER node

WORKDIR /workspace

ENTRYPOINT ["/entrypoint.sh"]
CMD ["bash"]
