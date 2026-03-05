<?php

namespace App\Filament\Widgets;

use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OrganizationMembersWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Organization members';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getMembersQuery())
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
            ])
            ->headerActions([
                Action::make('attachUser')
                    ->label('Add member')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Select::make('user_id')
                            ->label('User')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => $this->getAttachableUserOptions()),
                    ])
                    ->action(fn (array $data): Notification => $this->attachUser((int) $data['user_id'])),
            ])
            ->actions([
                Action::make('detachUser')
                    ->label('Remove')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->disabled(fn (User $record): bool => (int) $record->getKey() === (int) auth()->id())
                    ->action(fn (User $record): Notification => $this->detachUser($record)),
            ])
            ->paginated([10, 25, 50]);
    }

    /**
     * @return Builder<User>
     */
    private function getMembersQuery(): Builder
    {
        $tenant = $this->getTenant();

        if (! $tenant) {
            return User::query()->whereKey(-1);
        }

        return User::query()
            ->whereHas('organizations', fn (Builder $query): Builder => $query->whereKey($tenant->getKey()));
    }

    /**
     * @return array<int, string>
     */
    private function getAttachableUserOptions(): array
    {
        $tenant = $this->getTenant();

        if (! $tenant) {
            return [];
        }

        return User::query()
            ->whereDoesntHave('organizations', fn (Builder $query): Builder => $query->whereKey($tenant->getKey()))
            ->orderBy('name')
            ->pluck('email', 'id')
            ->all();
    }

    private function attachUser(int $userId): Notification
    {
        $tenant = $this->getTenant();

        if (! $tenant) {
            return Notification::make()
                ->title('No organization selected')
                ->danger()
                ->send();
        }

        $tenant->users()->syncWithoutDetaching([$userId]);

        return Notification::make()
            ->title('Member added')
            ->success()
            ->send();
    }

    private function detachUser(User $user): Notification
    {
        $tenant = $this->getTenant();

        if (! $tenant) {
            return Notification::make()
                ->title('No organization selected')
                ->danger()
                ->send();
        }

        if ((int) $user->getKey() === (int) auth()->id()) {
            return Notification::make()
                ->title('You cannot remove yourself')
                ->warning()
                ->send();
        }

        $tenant->users()->detach($user->getKey());

        return Notification::make()
            ->title('Member removed')
            ->success()
            ->send();
    }

    private function getTenant(): ?Organization
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return null;
        }

        return $tenant;
    }
}
