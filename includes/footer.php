        </main>
    </div>
    <script>
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        window.initializeCustomSelects = function() {
            document.querySelectorAll('select.select:not(.custom-select-initialized)').forEach(select => {
                select.classList.add('custom-select-initialized');
                select.style.opacity = '0';
                select.style.position = 'absolute';
                select.style.pointerEvents = 'none';
                select.style.height = '0';
                select.style.width = '0';
                
                let wrapper = document.createElement('div');
                wrapper.style.position = 'relative';
                wrapper.style.width = '100%';
                select.parentNode.insertBefore(wrapper, select);
                wrapper.appendChild(select);
                
                let trigger = document.createElement('div');
                trigger.className = select.className.replace('custom-select-initialized', '').trim() + ' custom-select-trigger';
                trigger.style.display = 'flex';
                trigger.style.alignItems = 'center';
                trigger.style.justifyContent = 'space-between';
                trigger.style.cursor = 'pointer';
                
                let valueSpan = document.createElement('span');
                valueSpan.textContent = select.options[select.selectedIndex]?.text || '';
                valueSpan.style.whiteSpace = 'nowrap';
                valueSpan.style.overflow = 'hidden';
                valueSpan.style.textOverflow = 'ellipsis';
                
                trigger.appendChild(valueSpan);
                wrapper.appendChild(trigger);
                
                let optionsList = document.createElement('div');
                optionsList.className = 'custom-select-options';
                optionsList.style.position = 'absolute';
                optionsList.style.top = 'calc(100% + 4px)';
                optionsList.style.left = '0';
                optionsList.style.width = '100%';
                optionsList.style.zIndex = '50';
                optionsList.style.display = 'none';
                optionsList.style.maxHeight = '250px';
                optionsList.style.overflowY = 'auto';
                optionsList.style.backgroundColor = 'var(--background)';
                optionsList.style.border = '1px solid var(--border)';
                optionsList.style.borderRadius = 'var(--radius-md)';
                optionsList.style.boxShadow = '0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)';
                optionsList.style.padding = '0.25rem';
                
                Array.from(select.options).forEach(opt => {
                    let optDiv = document.createElement('div');
                    optDiv.textContent = opt.text;
                    optDiv.style.padding = '0.375rem 1.5rem 0.375rem 0.5rem';
                    optDiv.style.cursor = 'pointer';
                    optDiv.style.fontSize = '0.875rem';
                    optDiv.style.borderRadius = 'var(--radius-sm)';
                    optDiv.style.color = 'var(--foreground)';
                    
                    optDiv.addEventListener('mouseenter', () => optDiv.style.backgroundColor = 'var(--secondary)');
                    optDiv.addEventListener('mouseleave', () => optDiv.style.backgroundColor = 'transparent');
                    
                    optDiv.addEventListener('click', (e) => {
                        e.stopPropagation();
                        select.value = opt.value;
                        valueSpan.textContent = opt.text;
                        optionsList.style.display = 'none';
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    optionsList.appendChild(optDiv);
                });
                
                wrapper.appendChild(optionsList);
                
                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    let isClosed = optionsList.style.display === 'none';
                    document.querySelectorAll('.custom-select-options').forEach(o => {
                        o.style.display = 'none';
                        if (o.parentNode) o.parentNode.style.zIndex = '';
                    });
                    if (isClosed) {
                        optionsList.style.display = 'block';
                        wrapper.style.zIndex = '9999';
                    }
                });
                
                document.addEventListener('click', () => {
                    optionsList.style.display = 'none';
                    wrapper.style.zIndex = '';
                });
                
                select.addEventListener('change', () => {
                    valueSpan.textContent = select.options[select.selectedIndex]?.text || '';
                });
            });
        };
        
        window.initializeCustomSelects();
    </script>
</body>
</html>
