document.addEventListener('DOMContentLoaded', function() {
    const optionCards = document.querySelectorAll('.option-card');
    const surveyForm = document.getElementById('surveyForm');
    const btnNext = document.querySelector('.btn-next');
    const feedbackTextarea = document.querySelector('.feedback-textarea');
    const virtualKeyboard = document.getElementById('virtualKeyboard');

    // --- Form Safeguards ---
    if (surveyForm) {
        let lastClickedButton = null;

        // Track which button was clicked (more compatible than e.submitter for older browsers/kiosks)
        const allSubmitButtons = surveyForm.querySelectorAll('button[type="submit"]');
        allSubmitButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                lastClickedButton = this;
            });
        });

        // Prevent accidental submission via Enter key (especially on touch keyboards)
        surveyForm.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const activeElement = document.activeElement;
                // Allow enter only on buttons that are not disabled
                if (activeElement.tagName !== 'BUTTON' || activeElement.disabled) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Final sanity check on submission
        surveyForm.addEventListener('submit', function(e) {
            const responseInput = surveyForm.querySelector('[name="response"]:checked, textarea[name="response"]');
            // Use tracked button instead of e.submitter for broad compatibility
            const action = lastClickedButton ? lastClickedButton.value : '';
            
            // If going backwards (KEMBALI), always allow it
            if (action === 'prev') {
                return true;
            }

            // For next/submit, validate answers
            if (!responseInput || (responseInput.tagName === 'TEXTAREA' && responseInput.value.trim().length < 3)) {
                e.preventDefault();
                console.warn('Submission blocked: Response missing or too short.');
                return false;
            }
        });
    }

    // --- Survey Interactivity (Ratings) ---
    optionCards.forEach(card => {
        card.addEventListener('click', function() {
            const groupName = this.querySelector('input').name;
            const groupCards = document.querySelectorAll(`input[name="${groupName}"]`);
            
            groupCards.forEach(input => {
                input.closest('.option-card').classList.remove('selected');
            });

            this.classList.add('selected');
            // Trigger grid dimming logic
            const parentGrid = this.closest('.survey-options-grid');
            if (parentGrid) parentGrid.classList.add('has-selection');

            this.querySelector('input').checked = true;

            this.style.transform = 'scale(0.95)';
            setTimeout(() => this.style.transform = '', 100);

            // Trigger Fireworks (Confetti) burst
            const rect = this.getBoundingClientRect();
            const x = (rect.left + rect.width / 2) / window.innerWidth;
            const y = (rect.top + rect.height / 2) / window.innerHeight;

            confetti({
                particleCount: 150,
                spread: 70,
                origin: { x, y },
                colors: ['#facc15', '#fbbf24', '#f59e0b', '#d97706'], // Warm golden colors
                zIndex: 2000,
                ticks: 300,
                gravity: 1.2,
                scalar: 1,
                shapes: ['circle', 'square']
            });

            // Auto-advance with smooth transition
            setTimeout(() => {
                const container = document.getElementById('surveyContainer');
                if (container) {
                    container.classList.remove('animate-[fadeInScale_0.6s_ease-out]');
                    container.classList.add('animate-[fadeOutScale_0.4s_ease-in_forwards]');
                }
                
                setTimeout(() => {
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = (btnNext && btnNext.value) ? btnNext.value : 'next';
                    surveyForm.appendChild(actionInput);
                    surveyForm.submit();
                }, 400); // Wait for fade-out animation to complete
            }, 600); // Initial delay after clicking emoji
        });
    });

    // --- Virtual Keyboard Logic ---
    if (feedbackTextarea && virtualKeyboard) {
        const toggleBtn = document.getElementById('toggleKeyboardBtn');
        const toggleLabel = document.getElementById('toggleKeyboardLabel');
        let useVirtualKeyboard = false;

        const keyboardLayout = [
            ["1", "2", "3", "4", "5", "6", "7", "8", "9", "0", "backspace"],
            ["q", "w", "e", "r", "t", "y", "u", "i", "o", "p"],
            ["a", "s", "d", "f", "g", "h", "j", "k", "l"],
            ["shift", "z", "x", "c", "v", "b", "n", "m", "," , ".", "?", "!"],
            ["space", "enter"]
        ];

        let isShifted = false;

        function generateKeyboard() {
            virtualKeyboard.innerHTML = '';
            keyboardLayout.forEach(row => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'flex justify-center gap-2 mb-2';
                
                row.forEach(key => {
                    const keyBtn = document.createElement('div');
                    keyBtn.className = 'key';
                    keyBtn.dataset.key = key;

                    let label = key;
                    if (key === 'backspace') label = '<i class="fa-solid fa-backspace"></i>';
                    else if (key === 'shift') label = '<i class="fa-solid fa-arrow-up"></i>';
                    else if (key === 'space') label = 'SPACE';
                    else if (key === 'enter') label = 'ENTER';
                    else label = isShifted ? key.toUpperCase() : key;

                    keyBtn.innerHTML = label;
                    keyBtn.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        handleKeyPress(key);
                    });
                    rowDiv.appendChild(keyBtn);
                });
                virtualKeyboard.appendChild(rowDiv);
            });
        }

        function handleKeyPress(key) {
            const start = feedbackTextarea.selectionStart;
            const end = feedbackTextarea.selectionEnd;
            const text = feedbackTextarea.value;

            if (key === 'backspace') {
                feedbackTextarea.value = text.substring(0, Math.max(0, start - 1)) + text.substring(end);
                feedbackTextarea.selectionStart = feedbackTextarea.selectionEnd = start - 1;
            } else if (key === 'shift') {
                isShifted = !isShifted;
                generateKeyboard();
            } else if (key === 'space') {
                feedbackTextarea.value = text.substring(0, start) + " " + text.substring(end);
                feedbackTextarea.selectionStart = feedbackTextarea.selectionEnd = start + 1;
            } else if (key === 'enter') {
                virtualKeyboard.classList.remove('keyboard-active');
                if (!btnNext.disabled) btnNext.click();
            } else {
                const char = isShifted ? key.toUpperCase() : key;
                feedbackTextarea.value = text.substring(0, start) + char + text.substring(end);
                feedbackTextarea.selectionStart = feedbackTextarea.selectionEnd = start + 1;
                if (isShifted) {
                    isShifted = false;
                    generateKeyboard();
                }
            }

            validateTextarea();
            feedbackTextarea.focus();
        }

        function validateTextarea() {
            if (feedbackTextarea.value.trim().length > 3) {
                btnNext.disabled = false;
                btnNext.classList.add('pulse');
            } else {
                btnNext.disabled = true;
                btnNext.classList.remove('pulse');
            }
        }

        // Toggle button click handler
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                useVirtualKeyboard = !useVirtualKeyboard;
                if (useVirtualKeyboard) {
                    virtualKeyboard.classList.add('keyboard-active');
                    generateKeyboard();
                    toggleLabel.textContent = 'Sembunyikan Keyboard Virtual';
                    toggleBtn.classList.add('bg-amber-100', 'text-amber-700', 'border-amber-300');
                    toggleBtn.classList.remove('bg-white/60', 'text-slate-600', 'border-white/70');
                    feedbackTextarea.focus();
                } else {
                    virtualKeyboard.classList.remove('keyboard-active');
                    toggleLabel.textContent = 'Gunakan Keyboard Virtual';
                    toggleBtn.classList.remove('bg-amber-100', 'text-amber-700', 'border-amber-300');
                    toggleBtn.classList.add('bg-white/60', 'text-slate-600', 'border-white/70');
                }
            });
        }

        // Hide keyboard when clicking outside (only if virtual keyboard is active)
        document.addEventListener('click', (e) => {
            if (useVirtualKeyboard && !virtualKeyboard.contains(e.target) && e.target !== feedbackTextarea && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                virtualKeyboard.classList.remove('keyboard-active');
                useVirtualKeyboard = false;
                toggleLabel.textContent = 'Gunakan Keyboard Virtual';
                toggleBtn.classList.remove('bg-amber-100', 'text-amber-700', 'border-amber-300');
                toggleBtn.classList.add('bg-white/60', 'text-slate-600', 'border-white/70');
            }
        });

        feedbackTextarea.addEventListener('input', validateTextarea);
    }

    // --- Celebration Fireworks on Thank You Screen ---
    const successScreen = document.querySelector('.fa-heart-circle-check');
    if (successScreen && typeof confetti === 'function') {
        // Grand firework burst sequence
        const duration = 3000;
        const end = Date.now() + duration;
        const colors = ['#facc15', '#fbbf24', '#f59e0b', '#d97706', '#ef4444', '#10b981'];

        function frame() {
            confetti({
                particleCount: 4,
                angle: 60,
                spread: 55,
                origin: { x: 0, y: 0.7 },
                colors: colors,
                zIndex: 2000
            });
            confetti({
                particleCount: 4,
                angle: 120,
                spread: 55,
                origin: { x: 1, y: 0.7 },
                colors: colors,
                zIndex: 2000
            });

            if (Date.now() < end) {
                requestAnimationFrame(frame);
            }
        }

        // Initial big burst from center
        setTimeout(() => {
            confetti({
                particleCount: 200,
                spread: 100,
                origin: { x: 0.5, y: 0.5 },
                colors: colors,
                zIndex: 2000,
                ticks: 400,
                gravity: 0.8,
                scalar: 1.2,
                shapes: ['circle', 'square']
            });
        }, 300);

        // Side-stream continuous bursts
        setTimeout(frame, 600);
    }

    // --- Help Modal Logic ---
    const helpModal = document.getElementById('helpModal');
    const helpModalContent = document.getElementById('helpModalContent');
    const openHelpBtn = document.getElementById('openHelpBtn');
    const closeHelpBtn = document.getElementById('closeHelpBtn');
    const helpModalBackdrop = document.getElementById('helpModalBackdrop');

    if (openHelpBtn && helpModal) {
        openHelpBtn.addEventListener('click', (e) => {
            e.preventDefault();
            helpModal.classList.add('modal-active');
            setTimeout(() => {
                helpModalContent.classList.add('modal-content-active');
            }, 10);
        });

        const closeModal = () => {
            helpModalContent.classList.remove('modal-content-active');
            setTimeout(() => {
                helpModal.classList.remove('modal-active');
            }, 300);
        };

        if (closeHelpBtn) closeHelpBtn.addEventListener('click', closeModal);
        if (helpModalBackdrop) helpModalBackdrop.addEventListener('click', closeModal);
        const closeHelpIconBtn = document.getElementById('closeHelpIconBtn');
        if (closeHelpIconBtn) closeHelpIconBtn.addEventListener('click', closeModal);
    }
});
