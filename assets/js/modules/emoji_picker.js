/**
 * Module Emoji Picker - Sélecteur d'emoji réutilisable
 * Basé sur la maquette emoji.webp
 */

export class EmojiPicker {
  constructor() {
    this.t = window.__zasamix_t || ((k, d) => d || k);
    const t = this.t;
    this.picker = null;
    this.currentCategory = 'smileys';
    this.searchTerm = '';

    // Catégories d'emojis
    this.categories = {
      recent: {
        icon: '🕐',
        label: t('emoji.category_recent', 'Récemment utilisés'),
        emojis: this.getRecentEmojis()
      },
      smileys: {
        icon: '😀',
        label: t('emoji.category_smileys', 'Smileys'),
        emojis: ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙', '🥲', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫', '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵', '🥶', '😶‍🌫️', '🥴', '😵', '🤯', '🤠', '🥳', '🥸', '😎', '🤓', '🧐', '😕', '😟', '🙁', '☹️', '😮', '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '👍', '👎', '👊', '✊', '🤛', '🤜', '🤞', '✌️', '🤟', '🤘', '👌', '🤌', '🤏', '👈', '👉', '👆', '👇', '☝️', '✋', '🤚', '🖐️', '🖖', '👋', '🤙', '💪', '🦾', '🖕', '✍️', '🙏']
      },
      animals: {
        icon: '🐻',
        label: t('emoji.category_animals', 'Animaux & Nature'),
        emojis: ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐻‍❄️', '🐨', '🐯', '🦁', '🐮', '🐷', '🐽', '🐸', '🐵', '🙈', '🙉', '🙊', '🐒', '🐔', '🐧', '🐦', '🐤', '🐣', '🐥', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗', '🐴', '🦄', '🐝', '🪱', '🐛', '🦋', '🐌', '🐞', '🐜', '🪰', '🪲', '🪳', '🦟', '🦗', '🕷️', '🕸️', '🦂', '🐢', '🐍', '🦎', '🦖', '🦕', '🐙', '🦑', '🦐', '🦞', '🦀', '🐡', '🐠', '🐟', '🐬', '🐳', '🐋', '🦈', '🐊', '🐅', '🐆', '🦓', '🦍', '🦧', '🦣', '🐘', '🦛', '🦏', '🐪', '🐫', '🦒', '🦘', '🦬', '🐃', '🐂', '🐄', '🐎', '🐖', '🐏', '🐑', '🦙', '🐐', '🦌', '🐕', '🐩', '🦮', '🐕‍🦺', '🐈', '🐈‍⬛', '🪶', '🐓', '🦃', '🦤', '🦚', '🦜', '🦢', '🦩', '🕊️', '🐇', '🦝', '🦨', '🦡', '🦫', '🦦', '🦥', '🐁', '🐀', '🐿️', '🦔', '🌲', '🌳', '🌴', '🌱', '🌿', '☘️', '🍀', '🎍', '🪴', '🎋', '🍃', '🍂', '🍁', '🍄', '🐚', '🪨', '🌾', '💐', '🌷', '🌹', '🥀', '🌺', '🌸', '🌼', '🌻', '🌞', '🌝', '🌛', '🌜', '🌚', '🌕', '🌖', '🌗', '🌘', '🌑', '🌒', '🌓', '🌔', '🌙', '🌎', '🌍', '🌏', '🪐', '💫', '⭐', '🌟', '✨', '⚡', '☄️', '💥', '🔥', '🌪️', '🌈', '☀️', '🌤️', '⛅', '🌥️', '☁️', '🌦️', '🌧️', '⛈️', '🌩️', '🌨️', '❄️', '☃️', '⛄', '🌬️', '💨', '💧', '💦', '☔', '☂️', '🌊', '🌫️']
      },
      food: {
        icon: '🍔',
        label: t('emoji.category_food', 'Nourriture'),
        emojis: ['🍏', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🫐', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥', '🥝', '🍅', '🍆', '🥑', '🥦', '🥬', '🥒', '🌶️', '🫑', '🌽', '🥕', '🫒', '🧄', '🧅', '🥔', '🍠', '🥐', '🥯', '🍞', '🥖', '🥨', '🧀', '🥚', '🍳', '🧈', '🥞', '🧇', '🥓', '🥩', '🍗', '🍖', '🦴', '🌭', '🍔', '🍟', '🍕', '🫓', '🥪', '🥙', '🧆', '🌮', '🌯', '🫔', '🥗', '🥘', '🫕', '🥫', '🍝', '🍜', '🍲', '🍛', '🍣', '🍱', '🥟', '🦪', '🍤', '🍙', '🍚', '🍘', '🍥', '🥠', '🥮', '🍢', '🍡', '🍧', '🍨', '🍦', '🥧', '🧁', '🍰', '🎂', '🍮', '🍭', '🍬', '🍫', '🍿', '🍩', '🍪', '🌰', '🥜', '🍯', '🥛', '🍼', '🫖', '☕', '🍵', '🧃', '🥤', '🧋', '🍶', '🍺', '🍻', '🥂', '🍷', '🥃', '🍸', '🍹', '🧉', '🍾', '🧊', '🥄', '🍴', '🍽️', '🥣', '🥡', '🥢', '🧂']
      },
      travel: {
        icon: '✈️',
        label: t('emoji.category_travel', 'Voyages'),
        emojis: ['🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐', '🛻', '🚚', '🚛', '🚜', '🦯', '🦽', '🦼', '🛴', '🚲', '🛵', '🏍️', '🛺', '🚨', '🚔', '🚍', '🚘', '🚖', '🚡', '🚠', '🚟', '🚃', '🚋', '🚞', '🚝', '🚄', '🚅', '🚈', '🚂', '🚆', '🚇', '🚊', '🚉', '✈️', '🛫', '🛬', '🛩️', '💺', '🛰️', '🚀', '🛸', '🚁', '🛶', '⛵', '🚤', '🛥️', '🛳️', '⛴️', '🚢', '⚓', '🪝', '⛽', '🚧', '🚦', '🚥', '🚏', '🗺️', '🗿', '🗽', '🗼', '🏰', '🏯', '🏟️', '🎡', '🎢', '🎠', '⛲', '⛱️', '🏖️', '🏝️', '🏜️', '🌋', '⛰️', '🏔️', '🗻', '🏕️', '⛺', '🛖', '🏠', '🏡', '🏘️', '🏚️', '🏗️', '🏭', '🏢', '🏬', '🏣', '🏤', '🏥', '🏦', '🏨', '🏪', '🏫', '🏩', '💒', '🏛️', '⛪', '🕌', '🕍', '🛕', '🕋', '⛩️', '🛤️', '🛣️', '🗾', '🎑', '🏞️', '🌅', '🌄', '🌠', '🎇', '🎆', '🌇', '🌆', '🏙️', '🌃', '🌌', '🌉', '🌁']
      },
      activities: {
        icon: '⚽',
        label: t('emoji.category_activities', 'Activités'),
        emojis: ['⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🪃', '🥅', '⛳', '🪁', '🏹', '🎣', '🤿', '🥊', '🥋', '🎽', '🛹', '🛼', '🛷', '⛸️', '🥌', '🎿', '⛷️', '🏂', '🪂', '🏋️', '🤼', '🤸', '🤺', '⛹️', '🤾', '🏌️', '🏇', '🧘', '🏊', '🏄', '🚣', '🧗', '🚵', '🚴', '🏆', '🥇', '🥈', '🥉', '🏅', '🎖️', '🏵️', '🎗️', '🎫', '🎟️', '🎪', '🤹', '🎭', '🩰', '🎨', '🎬', '🎤', '🎧', '🎼', '🎹', '🥁', '🪘', '🎷', '🎺', '🪗', '🎸', '🪕', '🎻', '🎲', '♟️', '🎯', '🎳', '🎮', '🎰', '🧩']
      },
      objects: {
        icon: '💡',
        label: t('emoji.category_objects', 'Objets'),
        emojis: ['⌚', '📱', '📲', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '🖲️', '🕹️', '🗜️', '💽', '💾', '💿', '📀', '📼', '📷', '📸', '📹', '🎥', '📽️', '🎞️', '📞', '☎️', '📟', '📠', '📺', '📻', '🎙️', '🎚️', '🎛️', '🧭', '⏱️', '⏲️', '⏰', '🕰️', '⌛', '⏳', '📡', '🔋', '🔌', '💡', '🔦', '🕯️', '🪔', '🧯', '🛢️', '💸', '💵', '💴', '💶', '💷', '🪙', '💰', '💳', '💎', '⚖️', '🪜', '🧰', '🪛', '🔧', '🔨', '⚒️', '🛠️', '⛏️', '🪚', '🔩', '⚙️', '🪤', '🧱', '⛓️', '🧲', '🔫', '💣', '🧨', '🪓', '🔪', '🗡️', '⚔️', '🛡️', '🚬', '⚰️', '🪦', '⚱️', '🏺', '🔮', '📿', '🧿', '💈', '⚗️', '🔭', '🔬', '🕳️', '🩹', '🩺', '💊', '💉', '🩸', '🧬', '🦠', '🧫', '🧪', '🌡️', '🧹', '🪠', '🧺', '🧻', '🚽', '🚰', '🚿', '🛁', '🛀', '🧼', '🪥', '🪒', '🧽', '🪣', '🧴', '🛎️', '🔑', '🗝️', '🚪', '🪑', '🛋️', '🛏️', '🛌', '🧸', '🪆', '🖼️', '🪞', '🪟', '🛍️', '🛒', '🎁', '🎈', '🎏', '🎀', '🪄', '🪅', '🎊', '🎉', '🎎', '🏮', '🎐', '🧧', '✉️', '📩', '📨', '📧', '💌', '📥', '📤', '📦', '🏷️', '🪧', '📪', '📫', '📬', '📭', '📮', '📯', '📜', '📃', '📄', '📑', '🧾', '📊', '📈', '📉', '🗒️', '🗓️', '📆', '📅', '🗑️', '📇', '🗃️', '🗳️', '🗄️', '📋', '📁', '📂', '🗂️', '🗞️', '📰', '📓', '📔', '📒', '📕', '📗', '📘', '📙', '📚', '📖', '🔖', '🧷', '🔗', '📎', '🖇️', '📐', '📏', '🧮', '📌', '📍', '✂️', '🖊️', '🖋️', '✒️', '🖌️', '🖍️', '📝', '✏️', '🔍', '🔎', '🔏', '🔐', '🔒', '🔓']
      },
      symbols: {
        icon: '❤️',
        label: t('emoji.category_symbols', 'Symboles'),
        emojis: ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❤️‍🔥', '❤️‍🩹', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯', '🕎', '☯️', '☦️', '🛐', '⛎', '♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓', '🆔', '⚛️', '🉑', '☢️', '☣️', '📴', '📳', '🈶', '🈚', '🈸', '🈺', '🈷️', '✴️', '🆚', '💮', '🉐', '㊙️', '㊗️', '🈴', '🈵', '🈹', '🈲', '🅰️', '🅱️', '🆎', '🆑', '🅾️', '🆘', '❌', '⭕', '🛑', '⛔', '📛', '🚫', '💯', '💢', '♨️', '🚷', '🚯', '🚳', '🚱', '🔞', '📵', '🚭', '❗', '❕', '❓', '❔', '‼️', '⁉️', '🔅', '🔆', '〽️', '⚠️', '🚸', '🔱', '⚜️', '🔰', '♻️', '✅', '🈯', '💹', '❇️', '✳️', '❎', '🌐', '💠', 'Ⓜ️', '🌀', '💤', '🏧', '🚾', '♿', '🅿️', '🛗', '🈳', '🈂️', '🛂', '🛃', '🛄', '🛅', '🚹', '🚺', '🚼', '⚧️', '🚻', '🚮', '🎦', '📶', '🈁', '🔣', 'ℹ️', '🔤', '🔡', '🔠', '🆖', '🆗', '🆙', '🆒', '🆕', '🆓', '0️⃣', '1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣', '🔟', '🔢', '#️⃣', '*️⃣', '⏏️', '▶️', '⏸️', '⏯️', '⏹️', '⏺️', '⏭️', '⏮️', '⏩', '⏪', '⏫', '⏬', '◀️', '🔼', '🔽', '➡️', '⬅️', '⬆️', '⬇️', '↗️', '↘️', '↙️', '↖️', '↕️', '↔️', '↪️', '↩️', '⤴️', '⤵️', '🔀', '🔁', '🔂', '🔄', '🔃', '🎵', '🎶', '➕', '➖', '➗', '✖️', '🟰', '♾️', '💲', '💱', '™️', '©️', '®️', '〰️', '➰', '➿', '🔚', '🔙', '🔛', '🔝', '🔜', '✔️', '☑️', '🔘', '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '⚫', '⚪', '🟤', '🔺', '🔻', '🔸', '🔹', '🔶', '🔷', '🔳', '🔲', '▪️', '▫️', '◾', '◽', '◼️', '◻️', '🟥', '🟧', '🟨', '🟩', '🟦', '🟪', '⬛', '⬜', '🟫', '🔈', '🔇', '🔉', '🔊', '🔔', '🔕', '📣', '📢', '👁️‍🗨️', '💬', '💭', '🗯️', '♠️', '♣️', '♥️', '♦️', '🃏', '🎴', '🀄', '🕐', '🕑', '🕒', '🕓', '🕔', '🕕', '🕖', '🕗', '🕘', '🕙', '🕚', '🕛', '🕜', '🕝', '🕞', '🕟', '🕠', '🕡', '🕢', '🕣', '🕤', '🕥', '🕦', '🕧']
      },
      flags: {
        icon: '🏁',
        label: t('emoji.category_flags', 'Drapeaux'),
        emojis: ['🏁', '🚩', '🎌', '🏴', '🏳️', '🏳️‍🌈', '🏳️‍⚧️', '🏴‍☠️', '🇦🇨', '🇦🇩', '🇦🇪', '🇦🇫', '🇦🇬', '🇦🇮', '🇦🇱', '🇦🇲', '🇦🇴', '🇦🇶', '🇦🇷', '🇦🇸', '🇦🇹', '🇦🇺', '🇦🇼', '🇦🇽', '🇦🇿', '🇧🇦', '🇧🇧', '🇧🇩', '🇧🇪', '🇧🇫', '🇧🇬', '🇧🇭', '🇧🇮', '🇧🇯', '🇧🇱', '🇧🇲', '🇧🇳', '🇧🇴', '🇧🇶', '🇧🇷', '🇧🇸', '🇧🇹', '🇧🇻', '🇧🇼', '🇧🇾', '🇧🇿', '🇨🇦', '🇨🇨', '🇨🇩', '🇨🇫', '🇨🇬', '🇨🇭', '🇨🇮', '🇨🇰', '🇨🇱', '🇨🇲', '🇨🇳', '🇨🇴', '🇨🇵', '🇨🇷', '🇨🇺', '🇨🇻', '🇨🇼', '🇨🇽', '🇨🇾', '🇨🇿', '🇩🇪', '🇩🇬', '🇩🇯', '🇩🇰', '🇩🇲', '🇩🇴', '🇩🇿', '🇪🇦', '🇪🇨', '🇪🇪', '🇪🇬', '🇪🇭', '🇪🇷', '🇪🇸', '🇪🇹', '🇪🇺', '🇫🇮', '🇫🇯', '🇫🇰', '🇫🇲', '🇫🇴', '🇫🇷', '🇬🇦', '🇬🇧', '🇬🇩', '🇬🇪', '🇬🇫', '🇬🇬', '🇬🇭', '🇬🇮', '🇬🇱', '🇬🇲', '🇬🇳', '🇬🇵', '🇬🇶', '🇬🇷', '🇬🇸', '🇬🇹', '🇬🇺', '🇬🇼', '🇬🇾', '🇭🇰', '🇭🇲', '🇭🇳', '🇭🇷', '🇭🇹', '🇭🇺', '🇮🇨', '🇮🇩', '🇮🇪', '🇮🇱', '🇮🇲', '🇮🇳', '🇮🇴', '🇮🇶', '🇮🇷', '🇮🇸', '🇮🇹', '🇯🇪', '🇯🇲', '🇯🇴', '🇯🇵', '🇰🇪', '🇰🇬', '🇰🇭', '🇰🇮', '🇰🇲', '🇰🇳', '🇰🇵', '🇰🇷', '🇰🇼', '🇰🇾', '🇰🇿', '🇱🇦', '🇱🇧', '🇱🇨', '🇱🇮', '🇱🇰', '🇱🇷', '🇱🇸', '🇱🇹', '🇱🇺', '🇱🇻', '🇱🇾', '🇲🇦', '🇲🇨', '🇲🇩', '🇲🇪', '🇲🇫', '🇲🇬', '🇲🇭', '🇲🇰', '🇲🇱', '🇲🇲', '🇲🇳', '🇲🇴', '🇲🇵', '🇲🇶', '🇲🇷', '🇲🇸', '🇲🇹', '🇲🇺', '🇲🇻', '🇲🇼', '🇲🇽', '🇲🇾', '🇲🇿', '🇳🇦', '🇳🇨', '🇳🇪', '🇳🇫', '🇳🇬', '🇳🇮', '🇳🇱', '🇳🇴', '🇳🇵', '🇳🇷', '🇳🇺', '🇳🇿', '🇴🇲', '🇵🇦', '🇵🇪', '🇵🇫', '🇵🇬', '🇵🇭', '🇵🇰', '🇵🇱', '🇵🇲', '🇵🇳', '🇵🇷', '🇵🇸', '🇵🇹', '🇵🇼', '🇵🇾', '🇶🇦', '🇷🇪', '🇷🇴', '🇷🇸', '🇷🇺', '🇷🇼', '🇸🇦', '🇸🇧', '🇸🇨', '🇸🇩', '🇸🇪', '🇸🇬', '🇸🇭', '🇸🇮', '🇸🇯', '🇸🇰', '🇸🇱', '🇸🇲', '🇸🇳', '🇸🇴', '🇸🇷', '🇸🇸', '🇸🇹', '🇸🇻', '🇸🇽', '🇸🇾', '🇸🇿', '🇹🇦', '🇹🇨', '🇹🇩', '🇹🇫', '🇹🇬', '🇹🇭', '🇹🇯', '🇹🇰', '🇹🇱', '🇹🇲', '🇹🇳', '🇹🇴', '🇹🇷', '🇹🇹', '🇹🇻', '🇹🇼', '🇹🇿', '🇺🇦', '🇺🇬', '🇺🇲', '🇺🇳', '🇺🇸', '🇺🇾', '🇺🇿', '🇻🇦', '🇻🇨', '🇻🇪', '🇻🇬', '🇻🇮', '🇻🇳', '🇻🇺', '🇼🇫', '🇼🇸', '🇽🇰', '🇾🇪', '🇾🇹', '🇿🇦', '🇿🇲', '🇿🇼', '🏴󠁧󠁢󠁥󠁮󠁧󠁿', '🏴󠁧󠁢󠁳󠁣󠁴󠁿', '🏴󠁧󠁢󠁷󠁬󠁳󠁿']
      }
    };
  }

  /**
   * Initialise le sélecteur d'emoji
   * @param {HTMLElement} triggerButton - Bouton qui déclenche l'ouverture
   * @param {Function} onSelect - Callback appelé quand un emoji est sélectionné
   */
  init(triggerButton, onSelect) {
    if (!triggerButton) {
      console.error('[EmojiPicker] Bouton déclencheur introuvable');
      return;
    }

    this.triggerButton = triggerButton;
    this.onSelectCallback = onSelect;

    // Événement clic sur le bouton
    this._triggerClickHandler = (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.toggle();
    };
    this.triggerButton.addEventListener('click', this._triggerClickHandler);

    // Fermer si clic en dehors
    this._outsideClickHandler = (e) => {
      if (!e.target || !e.target.closest) return;
      if (this.picker &&
          !e.target.closest('.zkr-emoji-picker') &&
          !e.target.closest('.zkr-emoji-trigger')) {
        this.close();
      }
    };
    document.addEventListener('click', this._outsideClickHandler);
  }

  /**
   * Ouvre/ferme le picker
   */
  toggle() {
    if (this.picker) {
      this.close();
    } else {
      this.open();
    }
  }

  /**
   * Ouvre le picker
   */
  open() {
    if (this.picker) return;

    this.picker = this.createPicker();
    document.body.appendChild(this.picker);

    // Positionner le picker
    requestAnimationFrame(() => {
      this.position();
    });

    // Charger la catégorie actuelle
    this.loadCategory(this.currentCategory);

    // Suivre le scroll pour repositionner
    this.scrollHandler = () => this.position();
    window.addEventListener('scroll', this.scrollHandler, true);
    window.addEventListener('resize', this.scrollHandler);
  }

  /**
   * Ferme le picker
   */
  close() {
    if (this.picker) {
      this.picker.remove();
      this.picker = null;
    }

    // Retirer les event listeners
    if (this.scrollHandler) {
      window.removeEventListener('scroll', this.scrollHandler, true);
      window.removeEventListener('resize', this.scrollHandler);
      this.scrollHandler = null;
    }
  }

  /**
   * Crée l'élément DOM du picker
   */
  createPicker() {
    const div = document.createElement('div');
    div.className = 'zkr-emoji-picker';

    div.innerHTML = `
      <div class="zkr-emoji-header">
        <div class="zkr-emoji-tabs">
          ${Object.keys(this.categories).map(key => `
            <button
              class="zkr-emoji-tab ${key === this.currentCategory ? 'active' : ''}"
              data-category="${key}"
              title="${this.categories[key].label}"
            >
              ${this.categories[key].icon}
            </button>
          `).join('')}
        </div>
        <div class="zkr-emoji-search">
          <input type="text" placeholder="${this.t('emoji.search_placeholder', 'Rechercher...')}" class="zkr-emoji-search-input form-control">
          <i class="bi bi-search zkr-emoji-search-icon"></i>
        </div>
      </div>
      <div class="zkr-emoji-body">
        <div class="zkr-emoji-category-label">${this.categories[this.currentCategory].label}</div>
        <div class="zkr-emoji-grid"></div>
      </div>
    `;

    // Événements des onglets
    div.querySelectorAll('.zkr-emoji-tab').forEach(tab => {
      tab.addEventListener('click', (e) => {
        e.preventDefault();
        const category = tab.dataset.category;
        this.switchCategory(category);
      });
    });

    // Événement de recherche
    const searchInput = div.querySelector('.zkr-emoji-search-input');
    searchInput.addEventListener('input', (e) => {
      this.search(e.target.value);
    });

    return div;
  }

  /**
   * Positionne le picker par rapport au bouton
   */
  position() {
    if (!this.picker || !this.triggerButton) return;

    const btnRect = this.triggerButton.getBoundingClientRect();
    const pickerHeight = this.picker.offsetHeight;
    const pickerWidth = this.picker.offsetWidth;

    // Détecter la hauteur de la zone fixe en haut (topbar, navbar, pp-header)
    const topbar = document.querySelector('.topbar, .navbar, .pp-header, [class*="navbar"]');
    let topbarHeight = 0;
    if (topbar) {
      const topbarRect = topbar.getBoundingClientRect();
      // Si l'élément est sticky ou fixed, utiliser sa hauteur
      const topbarStyle = window.getComputedStyle(topbar);
      if (topbarStyle.position === 'sticky' || topbarStyle.position === 'fixed') {
        topbarHeight = topbarRect.bottom;
      }
    }
    const minTop = Math.max(topbarHeight, 10); // Au moins 10px du haut

    // Position par défaut: au-dessus du bouton
    let top = btnRect.top - pickerHeight - 8;
    let left = btnRect.left;
    let showBelow = false;

    // Si pas assez de place en haut OU dépasse la zone du haut, afficher en dessous
    if (top < minTop) {
      top = btnRect.bottom + 8;
      showBelow = true;
    }

    // Vérifier qu'on ne dépasse pas à droite
    if (left + pickerWidth > window.innerWidth - 10) {
      left = window.innerWidth - pickerWidth - 10;
    }

    // Vérifier qu'on ne dépasse pas à gauche
    if (left < 10) {
      left = 10;
    }

    // Calculer la position du triangle (caret) pour qu'il pointe vers le bouton
    const btnCenter = btnRect.left + (btnRect.width / 2);
    const caretOffset = btnCenter - left;

    this.picker.style.top = top + 'px';
    this.picker.style.left = left + 'px';
    this.picker.style.setProperty('--caret-offset', caretOffset + 'px');

    // Inverser le triangle si on affiche en dessous
    if (showBelow) {
      this.picker.classList.add('zkr-emoji-picker-below');
    } else {
      this.picker.classList.remove('zkr-emoji-picker-below');
    }
  }

  /**
   * Change de catégorie
   */
  switchCategory(category) {
    this.currentCategory = category;
    this.searchTerm = '';

    // Mettre à jour l'onglet actif
    this.picker.querySelectorAll('.zkr-emoji-tab').forEach(tab => {
      tab.classList.toggle('active', tab.dataset.category === category);
    });

    // Réinitialiser la recherche
    this.picker.querySelector('.zkr-emoji-search-input').value = '';

    // Charger la catégorie
    this.loadCategory(category);
  }

  /**
   * Charge les emojis d'une catégorie
   */
  loadCategory(category) {
    const grid = this.picker.querySelector('.zkr-emoji-grid');
    const label = this.picker.querySelector('.zkr-emoji-category-label');

    const cat = this.categories[category];
    label.textContent = cat.label;

    const emojis = cat.emojis;
    this.renderEmojis(emojis, grid);
  }

  /**
   * Recherche d'emojis
   */
  search(term) {
    this.searchTerm = term.toLowerCase().trim();
    const grid = this.picker.querySelector('.zkr-emoji-grid');
    const label = this.picker.querySelector('.zkr-emoji-category-label');

    if (!this.searchTerm) {
      this.loadCategory(this.currentCategory);
      return;
    }

    // Rechercher dans toutes les catégories
    const allEmojis = Object.values(this.categories)
      .flatMap(cat => cat.emojis)
      .filter((emoji, index, self) => self.indexOf(emoji) === index); // Unique

    label.textContent = this.t('emoji.search_results_for', 'Résultats pour "{term}"', { term });
    this.renderEmojis(allEmojis.slice(0, 100), grid); // Limiter à 100 résultats
  }

  /**
   * Affiche les emojis dans la grille
   */
  renderEmojis(emojis, grid) {
    grid.innerHTML = emojis.map(emoji =>
      `<button class="zkr-emoji-btn" type="button" data-emoji="${emoji}">${emoji}</button>`
    ).join('');

    // Événement clic sur emoji
    grid.querySelectorAll('.zkr-emoji-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const emoji = btn.dataset.emoji;
        this.selectEmoji(emoji);
      });
    });
  }

  /**
   * Sélectionne un emoji
   */
  selectEmoji(emoji) {
    // Ajouter aux récents
    this.addToRecent(emoji);

    // Appeler le callback
    if (this.onSelectCallback) {
      this.onSelectCallback(emoji);
    }

    // Fermer le picker
    this.close();
  }

  /**
   * Récupère les emojis récents depuis localStorage
   */
  getRecentEmojis() {
    try {
      const recent = localStorage.getItem('zkriva_recent_emojis');
      return recent ? JSON.parse(recent) : ['😀', '😊', '😂', '❤️', '👍', '🎉', '✨', '🔥'];
    } catch (e) {
      return ['😀', '😊', '😂', '❤️', '👍', '🎉', '✨', '🔥'];
    }
  }

  /**
   * Ajoute un emoji aux récents
   */
  addToRecent(emoji) {
    try {
      let recent = this.getRecentEmojis();

      // Retirer l'emoji s'il existe déjà
      recent = recent.filter(e => e !== emoji);

      // Ajouter en premier
      recent.unshift(emoji);

      // Garder max 30 emojis
      recent = recent.slice(0, 30);

      localStorage.setItem('zkriva_recent_emojis', JSON.stringify(recent));

      // Mettre à jour la catégorie récents
      this.categories.recent.emojis = recent;
    } catch (e) {
      console.error('[EmojiPicker] Erreur sauvegarde récents:', e);
    }
  }

  /**
   * Détruit le picker (nettoyage)
   */
  destroy() {
    this.close();
    if (this._outsideClickHandler) {
      document.removeEventListener('click', this._outsideClickHandler);
      this._outsideClickHandler = null;
    }
    if (this.triggerButton && this._triggerClickHandler) {
      this.triggerButton.removeEventListener('click', this._triggerClickHandler);
      this._triggerClickHandler = null;
    }
    this.triggerButton = null;
    this.onSelectCallback = null;
  }
}
