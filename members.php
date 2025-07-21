<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access this page
require_login(); //

$page_title = "Members | CrimeAlert";
$current_user_id = $_SESSION['user_id']; //

// Fetch all users except the currently logged-in one
try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id != ? ORDER BY username ASC"); //
    $stmt->execute([$current_user_id]); //
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC); //
} catch (PDOException $e) {
    error_log("Error fetching members: " . $e->getMessage());
    $members = [];
    $error_message = "Could not load member list.";
}

require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4">
        <h1 class="text-3xl font-bold text-gray-800">Community Members</h1>
        <div class="relative w-full sm:w-72">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="search" id="member-search" placeholder="Search members..."
                   class="w-full pl-10 pr-4 py-2 border rounded-full bg-white shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
        </div>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <div id="member-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php if (empty($members)): ?>
            <p class="text-gray-500 col-span-full text-center">No other members found in the community yet.</p>
        <?php else: ?>
            <?php foreach ($members as $member): ?>
                <div class="member-card bg-white rounded-xl shadow-md p-6 text-center flex flex-col items-center transition-transform transform hover:scale-105 hover:shadow-lg">
                    <div class="h-20 w-20 rounded-full bg-indigo-500 text-white flex items-center justify-center font-bold text-3xl mb-4 border-4 border-white shadow-inner">
                        <?= strtoupper(substr($member['username'], 0, 1)) ?>
                    </div>
                    
                    <h3 class="member-name text-lg font-semibold text-gray-800 mb-4 truncate w-full" title="<?= htmlspecialchars($member['username']) ?>">
                        <?= htmlspecialchars($member['username']) ?>
                    </h3>
                    
                    <a href="chat.php?user_id=<?= $member['id'] ?>" class="mt-auto w-full bg-indigo-600 text-white px-4 py-2 text-sm rounded-lg hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-comments mr-2"></i>Chat Now
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div id="no-results-message" class="hidden text-center text-gray-500 mt-8">
        <i class="fas fa-user-slash fa-3x mb-4 text-gray-400"></i>
        <p class="text-xl">No members found matching your search.</p>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('member-search');
    const memberGrid = document.getElementById('member-grid');
    const memberCards = memberGrid.getElementsByClassName('member-card');
    const noResultsMessage = document.getElementById('no-results-message');

    searchInput.addEventListener('input', function() {
        const searchTerm = searchInput.value.toLowerCase();
        let visibleCount = 0;

        for (let i = 0; i < memberCards.length; i++) {
            const card = memberCards[i];
            const name = card.querySelector('.member-name').textContent.toLowerCase();

            if (name.includes(searchTerm)) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        }
        
        // Show or hide the "no results" message
        if (visibleCount === 0 && memberCards.length > 0) {
            noResultsMessage.style.display = 'block';
        } else {
            noResultsMessage.style.display = 'none';
        }
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>
