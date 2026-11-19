/* KitsuPlay Web player — HTML5 + hls.js, with the app's resume behaviour. */
(function () {
    "use strict";

    const root = document.querySelector("[data-player]");
    if (!root) return;

    let payload;
    try {
        payload = JSON.parse(root.getAttribute("data-payload") || "{}");
    } catch (error) {
        return;
    }

    const video = root.querySelector("[data-video]");
    const spinner = root.querySelector("[data-spinner]");
    const errorBox = root.querySelector("[data-player-error]");
    const errorTitle = root.querySelector("[data-error-title]");
    const errorBody = root.querySelector("[data-error-body]");
    const bigPlay = root.querySelector("[data-big-play]");
    const controls = root.querySelector("[data-controls]");
    const seek = root.querySelector("[data-seek]");
    const buffered = root.querySelector("[data-buffered]");
    const playBtn = root.querySelector("[data-play]");
    const muteBtn = root.querySelector("[data-mute]");
    const volume = root.querySelector("[data-volume]");
    const timeCurrent = root.querySelector("[data-time-current]");
    const timeTotal = root.querySelector("[data-time-total]");
    const status = document.querySelector("[data-player-status]");
    const speedLabel = root.querySelector("[data-speed-label]");
    const qualityLabel = root.querySelector("[data-quality-label]");
    const audioLabel = root.querySelector("[data-audio-label]");
    const autoplayLabel = document.querySelector("[data-autoplay-label]");
    const autoplayToggle = document.querySelector("[data-autoplay-toggle]");
    const menus = {
        speed: root.querySelector('[data-menu="speed"]'),
        subs: root.querySelector('[data-menu="subs"]'),
        quality: root.querySelector('[data-menu="quality"]'),
        audio: root.querySelector('[data-menu="audio"]'),
    };

    const prefs = (window.KitsuPrefs && window.KitsuPrefs.read()) || {
        autoplayNext: true,
        preferredQuality: "auto",
        subtitlesOn: false,
        subtitleLanguage: "en",
        reducedData: false,
    };
    const csrfMeta = document.querySelector('meta[name="csrf"]');
    const csrf = csrfMeta ? csrfMeta.getAttribute("content") || "" : "";

    let hls = null;
    let sources = [];
    let sourceIndex = 0;
    let resumeAt = Number(payload.resume || 0);
    let saveTimer = null;
    let lastSaved = 0;
    let recovering = false;
    let idleTimer = null;

    // ── small helpers ───────────────────────────────────────────────────────

    function clock(seconds) {
        if (!isFinite(seconds) || seconds < 0) seconds = 0;
        const total = Math.floor(seconds);
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        if (h > 0) return h + ":" + String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
        return m + ":" + String(s).padStart(2, "0");
    }

    function setStatus(text) {
        if (status) status.textContent = text;
    }

    function showSpinner(show) {
        if (spinner) spinner.hidden = !show;
    }

    function showError(title, body) {
        if (errorTitle) errorTitle.textContent = title;
        if (errorBody) errorBody.textContent = body;
        if (errorBox) errorBox.hidden = false;
        if (bigPlay) bigPlay.hidden = true;
        showSpinner(false);
        setStatus("Failed");
    }

    function hideError() {
        if (errorBox) errorBox.hidden = true;
        if (bigPlay) bigPlay.hidden = false;
    }

    function setPlaying(playing) {
        root.classList.toggle("is-playing", playing);
        if (playBtn) {
            const use = playBtn.querySelector("use");
            if (use) use.setAttribute("href", playing ? "#i-pause" : "#i-play");
            playBtn.setAttribute("aria-label", playing ? "Pause" : "Play");
        }
        setStatus(playing ? "Playing" : "Paused");
    }

    function closeMenus() {
        Object.values(menus).forEach(function (menu) {
            if (menu) menu.hidden = true;
        });
    }

    function toggleMenu(name) {
        const target = menus[name];
        const wasHidden = !target || target.hidden;
        closeMenus();
        if (target && wasHidden) target.hidden = false;
    }

    // ── playback ────────────────────────────────────────────────────────────

    function targetHeight() {
        if (prefs.reducedData) return 480;
        const map = { "1080p": 1080, "720p": 720, "480p": 480 };
        return map[prefs.preferredQuality] || 720;
    }

    function pickLevel() {
        if (!hls || prefs.preferredQuality === "auto") return -1;
        const wanted = targetHeight();
        let best = -1;
        let distance = Infinity;
        hls.levels.forEach(function (level, index) {
            const height = level.height || 0;
            const gap = Math.abs(height - wanted);
            if (gap < distance) {
                distance = gap;
                best = index;
            }
        });
        return best;
    }

    function attach(file) {
        hideError();
        recovering = false;
        showSpinner(true);
        setStatus("Loading");

        if (hls) {
            hls.destroy();
            hls = null;
        }
        video.removeAttribute("src");
        video.load();

        const isHls = file.kind === "hls";

        if (isHls && window.Hls && window.Hls.isSupported()) {
            hls = new window.Hls({
                enableWorker: true,
                lowLatencyMode: false,
                maxBufferLength: 30,
                backBufferLength: 30,
                startLevel: -1,
            });
            hls.loadSource(file.url);
            hls.attachMedia(video);

            hls.on(window.Hls.Events.MANIFEST_PARSED, function () {
                buildQualityMenu();
                const level = pickLevel();
                if (level >= 0) hls.startLevel = level;
                if (prefs.subtitlesOn) video.textTracks && enableSubtitles();
                play();
            });

            hls.on(window.Hls.Events.LEVEL_SWITCHED, function (_event, data) {
                if (!qualityLabel) return;
                if (hls.autoLevelEnabled) {
                    const level = hls.levels[data.level];
                    qualityLabel.textContent = level && level.height ? level.height + "p" : "Auto";
                }
            });

            hls.on(window.Hls.Events.SUBTITLE_TRACKS_UPDATED, function () {
                buildSubtitleMenu();
            });
            hls.on(window.Hls.Events.AUDIO_TRACKS_UPDATED, function () {
                buildAudioMenu();
            });
            hls.on(window.Hls.Events.ERROR, function (_event, data) {
                if (!data.fatal) return;
                if (data.type === window.Hls.ErrorTypes.NETWORK_ERROR && !recovering) {
                    recovering = true;
                    setStatus("Reconnecting");
                    try {
                        hls.startLoad();
                        return;
                    } catch (error) {
                        /* fall through to the file source */
                    }
                }
                if (data.type === window.Hls.ErrorTypes.MEDIA_ERROR) {
                    try {
                        hls.recoverMediaError();
                        return;
                    } catch (error) {
                        /* fall through */
                    }
                }
                fallbackOrFail("This stream stopped responding.");
            });
        } else if (isHls && video.canPlayType("application/vnd.apple.mpegurl")) {
            video.src = file.url;
            video.addEventListener("loadedmetadata", play, { once: true });
        } else {
            video.src = file.url;
            video.addEventListener("loadedmetadata", play, { once: true });
        }
    }

    function fallbackOrFail(reason) {
        const direct = sources.find(function (source) {
            return source.kind === "file";
        });
        if (direct && sources[sourceIndex] !== direct) {
            sourceIndex = sources.indexOf(direct);
            setStatus("Trying the direct file");
            attach(direct);
            return;
        }
        showError(
            "This stream could not be played",
            reason + " Try again, or pick another episode. If it keeps failing the source may still be scraping.",
        );
    }

    async function play() {
        try {
            await video.play();
            setPlaying(true);
        } catch (error) {
            // Autoplay with sound can be blocked; the big play button stays.
            setPlaying(false);
        }
    }

    function togglePlay() {
        if (video.paused) {
            void play();
        } else {
            video.pause();
        }
    }

    function applyResume() {
        if (resumeAt > 5 && isFinite(video.duration) && resumeAt < video.duration - 10) {
            try {
                video.currentTime = resumeAt;
            } catch (error) {
                /* seeking before the buffer is ready */
            }
        }
        resumeAt = 0;
    }

    async function load(season, episode) {
        showSpinner(true);
        setStatus("Finding a stream");
        try {
            const response = await fetch(
                payload.playUrl + "?ref=" + encodeURIComponent(payload.ref) + "&season=" + season + "&episode=" + episode,
                { headers: { Accept: "application/json" }, credentials: "same-origin" },
            );
            const data = await response.json();
            if (!response.ok || data.ok !== true || !data.sources || data.sources.length === 0) {
                showError("No source for this episode", data.error || "The catalogue has no playable stream yet.");
                return;
            }
            payload.season = data.season || season;
            payload.episode = data.episode || episode;
            sources = data.sources;
            sourceIndex = 0;
            resumeAt = 0;
            attach(sources[0]);
        } catch (error) {
            showError("The stream lookup failed", "Check your connection and try again.");
        }
    }

    // ── menus ───────────────────────────────────────────────────────────────

    function menuButton(label, sublabel, onClick, active) {
        const button = document.createElement("button");
        button.type = "button";
        button.textContent = label;
        if (sublabel) {
            const small = document.createElement("small");
            small.textContent = sublabel;
            button.appendChild(small);
        }
        if (active) button.classList.add("is-active");
        button.addEventListener("click", function () {
            onClick();
            closeMenus();
        });
        return button;
    }

    function buildQualityMenu() {
        if (!menus.quality || !hls) return;
        menus.quality.textContent = "";
        menus.quality.appendChild(
            menuButton("Auto", null, function () {
                hls.currentLevel = -1;
                hls.nextLevel = -1;
                if (qualityLabel) qualityLabel.textContent = "Auto";
            }, hls.autoLevelEnabled),
        );
        hls.levels.forEach(function (level, index) {
            const label = level.height ? level.height + "p" : Math.round(level.bitrate / 1000) + "k";
            menus.quality.appendChild(
                menuButton(label, level.bitrate ? Math.round(level.bitrate / 1000) + " kbps" : null, function () {
                    hls.currentLevel = index;
                    hls.nextLevel = index;
                    if (qualityLabel) qualityLabel.textContent = label;
                }, hls.currentLevel === index),
            );
        });
        const direct = sources.findIndex((source) => source.kind === "file");
        if (direct >= 0) {
            menus.quality.appendChild(
                menuButton("Direct file", "fallback", function () {
                    sourceIndex = direct;
                    attach(sources[direct]);
                }, false),
            );
        }
    }

    function buildSubtitleMenu() {
        if (!menus.subs) return;
        menus.subs.textContent = "";
        menus.subs.appendChild(
            menuButton("Off", null, function () {
                if (hls) hls.subtitleTrack = -1;
                for (let i = 0; i < video.textTracks.length; i++) video.textTracks[i].mode = "disabled";
            }, !prefs.subtitlesOn),
        );
        if (hls && hls.subtitleTracks && hls.subtitleTracks.length) {
            hls.subtitleTracks.forEach(function (track, index) {
                menus.subs.appendChild(
                    menuButton(
                        track.name || track.lang || "Track " + (index + 1),
                        track.lang || null,
                        function () {
                            hls.subtitleTrack = index;
                            hls.subtitleDisplay = true;
                        },
                        false,
                    ),
                );
            });
        } else {
            for (let i = 0; i < video.textTracks.length; i++) {
                const track = video.textTracks[i];
                menus.subs.appendChild(
                    menuButton(track.label || track.language || "Track " + (i + 1), track.language, function () {
                        for (let j = 0; j < video.textTracks.length; j++) {
                            video.textTracks[j].mode = j === i ? "showing" : "disabled";
                        }
                    }, false),
                );
            }
        }
    }

    function enableSubtitles() {
        if (!hls) return;
        hls.subtitleDisplay = true;
        const wanted = (prefs.subtitleLanguage || "en").toLowerCase();
        const index = (hls.subtitleTracks || []).findIndex(function (track) {
            return (track.lang || "").toLowerCase().startsWith(wanted);
        });
        hls.subtitleTrack = index >= 0 ? index : 0;
    }

    function buildAudioMenu() {
        if (!menus.audio) return;
        menus.audio.textContent = "";
        if (!hls || !hls.audioTracks || !hls.audioTracks.length) {
            const button = document.createElement("button");
            button.type = "button";
            button.textContent = "This stream has one audio track";
            button.disabled = true;
            menus.audio.appendChild(button);
            if (audioLabel) audioLabel.textContent = "Audio";
            return;
        }
        hls.audioTracks.forEach(function (track, index) {
            menus.audio.appendChild(
                menuButton(track.name || track.lang || "Audio " + (index + 1), track.lang || null, function () {
                    hls.audioTrack = index;
                    if (audioLabel) audioLabel.textContent = track.lang || "Audio";
                }, hls.audioTrack === index),
            );
        });
        if (audioLabel) audioLabel.textContent = hls.audioTracks[hls.audioTrack] ? hls.audioTracks[hls.audioTrack].lang || "Audio" : "Audio";
    }

    // ── progress ────────────────────────────────────────────────────────────

    function saveProgress() {
        if (!payload.progressUrl || !video.duration || video.duration < 30) return;
        const position = Math.floor(video.currentTime);
        if (position < 5 || Math.abs(position - lastSaved) < 5) return;
        lastSaved = position;
        const body = {
            action: "progress",
            item: {
                ref: payload.ref,
                title: payload.title,
                posterUrl: payload.poster,
                year: null,
                provider: payload.ref.split("-")[0],
                episodeCount: payload.episodeCount || 0,
            },
            season: payload.season,
            episode: payload.episode,
            positionSeconds: position,
            durationSeconds: Math.floor(video.duration),
            episodeCount: payload.episodeCount || 0,
            seasonCounts: payload.seasonCounts || {},
            runtimeMinutes: payload.runtimeMinutes || 0,
        };
        fetch(payload.progressUrl, {
            method: "POST",
            keepalive: true,
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF": csrf,
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(body),
        }).catch(function () {
            /* the next tick tries again */
        });
    }

    // ── wire the controls ───────────────────────────────────────────────────

    if (bigPlay) bigPlay.addEventListener("click", togglePlay);
    video.addEventListener("click", togglePlay);
    if (playBtn) playBtn.addEventListener("click", togglePlay);

    video.addEventListener("play", function () {
        setPlaying(true);
        if (saveTimer) clearInterval(saveTimer);
        saveTimer = setInterval(saveProgress, 10000);
    });
    video.addEventListener("pause", function () {
        setPlaying(false);
        saveProgress();
        if (saveTimer) clearInterval(saveTimer);
        saveTimer = null;
    });
    video.addEventListener("waiting", function () {
        showSpinner(true);
        setStatus("Buffering");
    });
    video.addEventListener("playing", function () {
        showSpinner(false);
        hideError();
        setPlaying(true);
    });
    video.addEventListener("canplay", function () {
        showSpinner(false);
    });
    video.addEventListener("loadedmetadata", function () {
        if (timeTotal) timeTotal.textContent = clock(video.duration);
        applyResume();
    });
    video.addEventListener("timeupdate", function () {
        if (timeCurrent) timeCurrent.textContent = clock(video.currentTime);
        if (seek && video.duration) {
            seek.value = String(Math.round((video.currentTime / video.duration) * 1000));
        }
        try {
            if (buffered && video.buffered.length && video.duration) {
                const end = video.buffered.end(video.buffered.length - 1);
                buffered.style.width = Math.min(100, (end / video.duration) * 100) + "%";
            }
        } catch (error) {
            /* buffered ranges can be empty right after a seek */
        }
    });
    video.addEventListener("ended", function () {
        saveProgress();
        setPlaying(false);
        setStatus("Finished");
        if (prefs.autoplayNext && payload.nextUrl) {
            setTimeout(() => window.location.assign(payload.nextUrl), 1200);
        }
    });
    video.addEventListener("error", function () {
        if (recovering) return;
        const code = video.error ? video.error.code : 0;
        const messages = {
            2: "The network dropped mid-stream.",
            3: "The file is damaged or uses a codec this browser cannot decode.",
            4: "This format is not supported by the browser.",
        };
        fallbackOrFail(messages[code] || "Playback failed.");
    });

    if (seek) {
        seek.addEventListener("input", function () {
            if (!video.duration) return;
            video.currentTime = (Number(seek.value) / 1000) * video.duration;
        });
    }

    if (volume) {
        volume.value = "100";
        volume.addEventListener("input", function () {
            video.volume = Number(volume.value) / 100;
            video.muted = video.volume === 0;
            updateVolumeIcon();
        });
    }

    function updateVolumeIcon() {
        if (!muteBtn) return;
        const use = muteBtn.querySelector("use");
        if (use) use.setAttribute("href", video.muted || video.volume === 0 ? "#i-muted" : "#i-volume");
    }

    if (muteBtn) {
        muteBtn.addEventListener("click", function () {
            video.muted = !video.muted;
            if (!video.muted && video.volume === 0) video.volume = 1;
            updateVolumeIcon();
        });
    }

    const back = root.querySelector("[data-back]");
    const forward = root.querySelector("[data-forward]");
    if (back) back.addEventListener("click", () => (video.currentTime = Math.max(0, video.currentTime - 10)));
    if (forward) forward.addEventListener("click", () => (video.currentTime = Math.min(video.duration || 0, video.currentTime + 10)));

    const speedToggle = root.querySelector("[data-speed-toggle]");
    if (speedToggle) speedToggle.addEventListener("click", () => toggleMenu("speed"));
    if (menus.speed) {
        menus.speed.querySelectorAll("[data-rate]").forEach(function (button) {
            button.addEventListener("click", function () {
                const rate = Number(button.getAttribute("data-rate"));
                video.playbackRate = rate;
                if (speedLabel) speedLabel.textContent = rate + "×";
                closeMenus();
            });
        });
    }

    root.querySelectorAll("[data-track-toggle]").forEach(function (button) {
        button.addEventListener("click", function () {
            toggleMenu(button.getAttribute("data-track-toggle"));
        });
    });

    root.querySelectorAll("[data-pip]").forEach(function (button) {
        button.addEventListener("click", async function () {
            try {
                if (document.pictureInPictureElement) {
                    await document.exitPictureInPicture();
                } else if (video.requestPictureInPicture) {
                    await video.requestPictureInPicture();
                }
            } catch (error) {
                setStatus("PiP unavailable");
            }
        });
    });

    root.querySelectorAll("[data-fullscreen]").forEach(function (button) {
        button.addEventListener("click", async function () {
            try {
                if (document.fullscreenElement) {
                    await document.exitFullscreen();
                } else if (root.requestFullscreen) {
                    await root.requestFullscreen();
                } else if (video.webkitEnterFullscreen) {
                    video.webkitEnterFullscreen();
                }
            } catch (error) {
                /* fullscreen refused */
            }
        });
    });

    const retry = root.querySelector("[data-retry]");
    if (retry) {
        retry.addEventListener("click", function () {
            hideError();
            attach(sources[sourceIndex] || sources[0]);
        });
    }

    // Auto-hide the controls while playing; any movement brings them back.
    function wake() {
        closeMenus();
        root.classList.remove("is-idle");
        if (idleTimer) clearTimeout(idleTimer);
        idleTimer = setTimeout(function () {
            if (!video.paused) root.classList.add("is-idle");
        }, 3200);
    }
    root.addEventListener("mousemove", wake);
    root.addEventListener("touchstart", wake, { passive: true });
    root.addEventListener("click", wake);
    if (controls) {
        controls.addEventListener("mouseenter", function () {
            if (idleTimer) clearTimeout(idleTimer);
            root.classList.remove("is-idle");
        });
    }

    // Keyboard shortcuts, ignored while typing in a field.
    document.addEventListener("keydown", function (event) {
        const tag = (event.target && event.target.tagName) || "";
        if (tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT") return;
        switch (event.key) {
            case " ":
            case "k":
                event.preventDefault();
                togglePlay();
                break;
            case "ArrowLeft":
                video.currentTime = Math.max(0, video.currentTime - 10);
                break;
            case "ArrowRight":
                video.currentTime = Math.min(video.duration || 0, video.currentTime + 10);
                break;
            case "ArrowUp":
                event.preventDefault();
                video.volume = Math.min(1, video.volume + 0.1);
                break;
            case "ArrowDown":
                event.preventDefault();
                video.volume = Math.max(0, video.volume - 0.1);
                break;
            case "m":
                video.muted = !video.muted;
                updateVolumeIcon();
                break;
            case "f":
                root.querySelector("[data-fullscreen]")?.click();
                break;
            case "p":
                root.querySelector("[data-pip]")?.click();
                break;
            case "c":
                toggleMenu("subs");
                break;
            default:
                break;
        }
    });

    // Autoplay preference switch under the player.
    if (autoplayToggle) {
        const update = function () {
            if (autoplayLabel) autoplayLabel.textContent = "Autoplay next: " + (prefs.autoplayNext ? "on" : "off");
        };
        update();
        autoplayToggle.addEventListener("click", function () {
            prefs.autoplayNext = !prefs.autoplayNext;
            if (window.KitsuPrefs) window.KitsuPrefs.write({ autoplayNext: prefs.autoplayNext });
            update();
        });
    }

    // Save the last position when the page is left.
    window.addEventListener("pagehide", saveProgress);
    document.addEventListener("visibilitychange", function () {
        if (document.visibilityState === "hidden") saveProgress();
    });

    updateVolumeIcon();
    setPlaying(false);
    wake();
    void load(Number(payload.season || 1), Number(payload.episode || 1));
})();
