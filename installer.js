class TravianInstaller {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 5;
        this.requirements = {};
        this.config = {};
        this.installProgress = 0;
        this.installLog = [];
    }

    init() {
        this.updateStepIndicator();
        this.updateNavigation();
    }

    updateStepIndicator() {
        for (let i = 1; i <= this.totalSteps; i++) {
            const step = document.querySelector(`[data-step="${i}"]`);
            if (i < this.currentStep) {
                step.classList.add('completed');
                step.classList.remove('active');
            } else if (i === this.currentStep) {
                step.classList.add('active');
                step.classList.remove('completed');
            } else {
                step.classList.remove('active', 'completed');
            }
        }
    }

    updateNavigation() {
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');

        prevBtn.style.display = this.currentStep > 1 ? 'block' : 'none';
        nextBtn.style.display = this.currentStep < this.totalSteps ? 'block' : 'none';

        if (this.currentStep === 4) {
            nextBtn.style.display = 'none';
        }
    }

    showStep(step) {
        document.querySelectorAll('.form-section').forEach(section => {
            section.classList.remove('active');
        });
        document.getElementById(`step${step}`).classList.add('active');
        this.currentStep = step;
        this.updateStepIndicator();
        this.updateNavigation();
    }

    nextStep() {
        if (this.currentStep < this.totalSteps) {
            this.showStep(this.currentStep + 1);
        }
    }

    previousStep() {
        if (this.currentStep > 1) {
            this.showStep(this.currentStep - 1);
        }
    }

    async checkRequirements() {
        const requirementsList = document.getElementById('requirements-list');
        requirementsList.innerHTML = '<div class="requirement-check"><i class="fas fa-spinner fa-spin"></i><span>Checking system requirements...</span></div>';

        try {
            const response = await fetch('api/check-requirements.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const result = await response.json();
            this.requirements = result;

            // Show detailed tabs
            document.getElementById('requirements-tabs').style.display = 'block';
            document.getElementById('support-btn').style.display = 'inline-block';

            // Categorize requirements
            const systemReqs = {};
            const phpReqs = {};
            const serviceReqs = {};

            for (const [key, requirement] of Object.entries(result)) {
                if (key.startsWith('php_ext_')) {
                    phpReqs[key] = requirement;
                } else if (key.startsWith('service_')) {
                    serviceReqs[key] = requirement;
                } else {
                    systemReqs[key] = requirement;
                }
            }

            // Display categorized requirements
            this.displayRequirements('system-requirements', systemReqs);
            this.displayRequirements('php-requirements', phpReqs);
            this.displayRequirements('services-requirements', serviceReqs);

            // Show summary in main list
            let html = '';
            for (const [key, requirement] of Object.entries(result)) {
                if (!key.startsWith('php_ext_') && !key.startsWith('service_')) {
                    const status = requirement.status;
                    const icon = status === 'pass' ? 'fa-check-circle' : 
                               status === 'fail' ? 'fa-times-circle' : 'fa-exclamation-triangle';
                    const className = status === 'pass' ? 'pass' : 
                                    status === 'fail' ? 'fail' : 'warning';

                    html += `
                        <div class="requirement-check ${className}">
                            <i class="fas ${icon}"></i>
                            <span>${requirement.name}: ${requirement.message}</span>
                        </div>
                    `;
                }
            }

            requirementsList.innerHTML = html;

            // Check if all critical requirements are met
            const criticalFailed = Object.values(result).some(req => 
                req.critical && req.status === 'fail'
            );

            if (criticalFailed) {
                this.addLog('error', 'Critical requirements not met. Please fix the issues above before proceeding.');
            } else {
                this.addLog('success', 'All requirements checked successfully!');
                setTimeout(() => this.nextStep(), 1500);
            }

        } catch (error) {
            this.addLog('error', 'Failed to check requirements: ' + error.message);
        }
    }

    displayRequirements(containerId, requirements) {
        const container = document.getElementById(containerId);
        let html = '';

        for (const [key, requirement] of Object.entries(requirements)) {
            const status = requirement.status;
            const icon = status === 'pass' ? 'fa-check-circle' : 
                       status === 'fail' ? 'fa-times-circle' : 'fa-exclamation-triangle';
            const className = status === 'pass' ? 'pass' : 
                            status === 'fail' ? 'fail' : 'warning';

            html += `
                <div class="requirement-check ${className}">
                    <i class="fas ${icon}"></i>
                    <span>${requirement.name}: ${requirement.message}</span>
                </div>
            `;
        }

        container.innerHTML = html;
    }

    async downloadSupportInfo() {
        try {
            const response = await fetch('api/support-info.php?action=download');
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'travian_installer_support_info.zip';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                this.addLog('success', 'Support information downloaded successfully!');
            } else {
                throw new Error('Failed to download support information');
            }
        } catch (error) {
            this.addLog('error', 'Failed to download support information: ' + error.message);
        }
    }

    async testDatabaseConnection() {
        const formData = this.getFormData('database-form');
        
        try {
            const response = await fetch('api/test-database.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();
            
            if (result.success) {
                this.addLog('success', 'Database connection successful!');
                this.config.database = formData;
                setTimeout(() => this.nextStep(), 1500);
            } else {
                this.addLog('error', 'Database connection failed: ' + result.message);
            }
        } catch (error) {
            this.addLog('error', 'Failed to test database connection: ' + error.message);
        }
    }

    validateServerConfig() {
        const formData = this.getFormData('server-form');
        
        // Basic validation
        if (!formData.server_name || !formData.admin_email) {
            this.addLog('error', 'Please fill in all required fields.');
            return;
        }

        this.config.server = formData;
        this.addLog('success', 'Server configuration validated successfully!');
        setTimeout(() => this.nextStep(), 1500);
    }

    async startInstallation() {
        const installBtn = document.getElementById('install-btn');
        installBtn.disabled = true;
        installBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Installing...';

        this.installProgress = 0;
        this.installLog = [];
        this.updateProgress();

        try {
            // Start installation process
            const response = await fetch('api/install.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    database: this.config.database,
                    server: this.config.server
                })
            });

            if (!response.ok) {
                throw new Error('Installation request failed');
            }

            // Poll for progress updates
            await this.pollInstallationProgress();

        } catch (error) {
            this.addLog('error', 'Installation failed: ' + error.message);
            installBtn.disabled = false;
            installBtn.innerHTML = '<i class="fas fa-redo"></i> Retry Installation';
        }
    }

    async pollInstallationProgress() {
        const maxAttempts = 300; // 5 minutes max
        let attempts = 0;

        const poll = async () => {
            try {
                const response = await fetch('api/install-progress.php');
                const result = await response.json();

                if (result.progress !== undefined) {
                    this.installProgress = result.progress;
                    this.updateProgress();
                }

                if (result.logs) {
                    result.logs.forEach(log => this.addLog(log.type, log.message));
                }

                if (result.status === 'completed') {
                    this.addLog('success', 'Installation completed successfully!');
                    setTimeout(() => this.nextStep(), 2000);
                    return;
                } else if (result.status === 'error') {
                    this.addLog('error', 'Installation failed: ' + result.message);
                    const installBtn = document.getElementById('install-btn');
                    installBtn.disabled = false;
                    installBtn.innerHTML = '<i class="fas fa-redo"></i> Retry Installation';
                    return;
                }

                attempts++;
                if (attempts < maxAttempts) {
                    setTimeout(poll, 1000);
                } else {
                    this.addLog('error', 'Installation timeout. Please check the server logs.');
                }

            } catch (error) {
                this.addLog('error', 'Failed to get installation progress: ' + error.message);
            }
        };

        poll();
    }

    updateProgress() {
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        const currentStep = document.getElementById('current-step');

        progressBar.style.width = this.installProgress + '%';
        progressText.textContent = this.installProgress + '%';

        // Update current step text based on progress
        if (this.installProgress < 20) {
            currentStep.textContent = 'Installing system packages...';
        } else if (this.installProgress < 40) {
            currentStep.textContent = 'Configuring database...';
        } else if (this.installProgress < 60) {
            currentStep.textContent = 'Setting up web server...';
        } else if (this.installProgress < 80) {
            currentStep.textContent = 'Installing application files...';
        } else if (this.installProgress < 100) {
            currentStep.textContent = 'Finalizing configuration...';
        } else {
            currentStep.textContent = 'Installation complete!';
        }
    }

    addLog(type, message) {
        const logContainer = document.getElementById('install-log');
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = document.createElement('div');
        logEntry.className = `log-entry log-${type}`;
        logEntry.innerHTML = `<span class="text-muted">[${timestamp}]</span> ${message}`;
        
        logContainer.appendChild(logEntry);
        logContainer.scrollTop = logContainer.scrollHeight;

        this.installLog.push({ type, message, timestamp });
    }

    getFormData(formId) {
        const form = document.getElementById(formId);
        const formData = new FormData(form);
        const data = {};
        
        for (const [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        return data;
    }
}

// Global functions for HTML onclick handlers
let installer;

function checkRequirements() {
    installer.checkRequirements();
}

function testDatabaseConnection() {
    installer.testDatabaseConnection();
}

function validateServerConfig() {
    installer.validateServerConfig();
}

function startInstallation() {
    installer.startInstallation();
}

function nextStep() {
    installer.nextStep();
}

function previousStep() {
    installer.previousStep();
}

function downloadSupportInfo() {
    installer.downloadSupportInfo();
}

// Initialize installer when page loads
document.addEventListener('DOMContentLoaded', function() {
    installer = new TravianInstaller();
    installer.init();
});
