FROM node:20-bookworm

# Install minimal system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    php-cli \
    php-xml \
    sudo \
    && rm -rf /var/lib/apt/lists/*

# Install GitHub CLI
RUN curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg | dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg \
    && chmod go+r /usr/share/keyrings/githubcli-archive-keyring.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | tee /etc/apt/sources.list.d/github-cli.list > /dev/null \
    && apt-get update \
    && apt-get install -y gh \
    && rm -rf /var/lib/apt/lists/*

# Configure node user (already exists in node:20 image with UID 1000)
# Give node user sudo access and create required directories
RUN echo "node ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers && \
    mkdir -p /workspace /home/node/.claude /home/node/.local/bin && \
    chown -R node:node /workspace /home/node/.claude /home/node/.local

# Switch to node user to install Claude Code natively (installs to ~/.local/bin)
USER node
RUN curl -fsSL https://claude.ai/install.sh | bash
USER root

# Create entrypoint script inline to avoid permission issues
RUN printf '#!/bin/bash\nsudo chown -R $(id -u):$(id -g) /home/node/.claude 2>/dev/null || true\nexec "$@"\n' > /entrypoint.sh && \
    chmod +x /entrypoint.sh

# Set environment variables
ENV DEVCONTAINER=true
ENV TERM=xterm-256color
ENV COLORTERM=truecolor
ENV PATH="/home/node/.local/bin:${PATH}"

# Switch to non-root user
USER node

WORKDIR /workspace

ENTRYPOINT ["/entrypoint.sh"]
CMD ["bash"]
