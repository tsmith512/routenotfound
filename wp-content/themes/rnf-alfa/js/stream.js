(async function() {
  const submitted = [];

  const sendVideoStats = async (player, id) => {
    // Only send this once
    if (submitted.includes(id)) {
      return;
    }

    let ranges = [];
    let totalWatched = 0;
    for (let i = 0; i < player.played.length; i++) {
      ranges.push({
        startTime: player.played.start(i),
        endTime: player.played.end(i)
      });
      totalWatched += (player.played.end(i) - player.played.start(i));
    }

    navigator.sendBeacon(`https://videostats.tsmithcreative.workers.dev/api/track`, JSON.stringify({
      videoId: id,
      ranges: ranges,
      percentComplete: Math.min((totalWatched / player.duration) * 100, 100),
    }));

    submitted.push(id);
  };

  document.querySelectorAll('iframe[src*="cloudflarestream"]').forEach(playerEl => {
    const player = Stream(playerEl);
    const id = playerEl.src.match(/[0-9a-f]{32}/)[0];

    if (!id) {
      return;
    }

    // @TODO: Ensure only one of these fires:
    player.addEventListener('ended', () => {
      sendVideoStats(player, id);
    });

    window.addEventListener('beforeunload', () => {
      sendVideoStats(player, id);
    });

    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "hidden") {
        sendVideoStats(player, id);
      }
    });
  });
})();
